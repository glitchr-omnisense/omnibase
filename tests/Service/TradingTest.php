<?php

namespace Tests\Base\Service;

use Base\Service\Model\Currency\CurrencyApiInterface;
use Base\Service\Trading;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The order Swap asks the currency providers in.
 *
 * TradingPass adds them in the container's order, which puts an application's
 * services before the bundles': a keyless fallback an application adds (a
 * central bank's reference rates, at a low priority) answered before the
 * keyed providers typed in the admin, whatever its priority said.
 *
 * And when they are asked at all: an application on a free plan (a hundred
 * requests a month) turns Trading offline, and only a caller that says so
 * goes online; everywhere else the stored rates, laid in as fallbacks, answer.
 */
class TradingTest extends TestCase
{
    private function names(Trading $trading): array
    {
        return array_values(array_map(fn ($provider) => $provider::class, $trading->getProviders()));
    }

    public function testTheHighestPriorityIsAskedFirstWhateverTheRegistrationOrder(): void
    {
        $trading = new Trading(new MockHttpClient(), sys_get_temp_dir());
        // Registered as the container does: the application's fallback first.
        $trading->addProvider(new CentralBankStub(-10));
        $trading->addProvider(new FixerStub(0));

        self::assertSame([FixerStub::class, CentralBankStub::class], $this->names($trading));
    }

    public function testEqualsKeepTheirRegistrationOrderAndTheKeylessAreLeftOut(): void
    {
        $trading = new Trading(new MockHttpClient(), sys_get_temp_dir());
        $trading->addProvider(new FixerStub(0));
        $trading->addProvider(new CurrencyLayerStub(0, null));
        $trading->addProvider(new CentralBankStub(0));

        self::assertSame([FixerStub::class, CentralBankStub::class], $this->names($trading));
    }

    /** @var list<string> the URLs the providers were asked */
    private array $requests = [];

    private RecordingCache $cache;

    /** A Trading with Fixer (a key typed in the admin) and the stored EUR/USD rate. */
    private function trading(bool $live = true): Trading
    {
        $this->requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->requests[] = $url;

            return new MockResponse(json_encode([
                'success' => true,
                'base' => 'EUR',
                'date' => '2026-09-29',
                'rates' => ['USD' => 1.25],
            ]));
        });

        $trading = new Trading($httpClient, sys_get_temp_dir(), $live);
        // Swap's cache, recorded: a rate cached on disk by an earlier run would
        // answer without asking the provider.
        $this->cache = new RecordingCache();
        (new \ReflectionProperty(Trading::class, 'simpleCache'))->setValue($trading, $this->cache);

        $trading->addProvider(new FixerApiStub(0));
        $trading->addFallback('EUR', 'USD', 1.10, '2026-09-01');

        return $trading;
    }

    public function testTheProvidersAreAskedByDefault(): void
    {
        $trading = $this->trading();

        self::assertTrue($trading->isLive());
        self::assertSame(1.25, $trading->getLatest('EUR', 'USD')->getValue());
        self::assertCount(1, $this->requests);
        self::assertStringContainsString('data.fixer.io', $this->requests[0]);
    }

    public function testOfflineTheStoredRatesAnswer(): void
    {
        $trading = $this->trading(live: false);

        self::assertFalse($trading->isLive());
        self::assertSame(1.10, $trading->getLatest('EUR', 'USD')->getValue());
        self::assertSame(1.10, $trading->get('EUR', 'USD')->getValue());
        self::assertEqualsWithDelta(11.0, $trading->convert(10, 'EUR', 'USD'), 1e-9);
        // No historical rate is stored.
        self::assertNull($trading->get('EUR', 'USD', [], '1 day'));
        self::assertSame([], $this->requests);
    }

    public function testOfflineTheProvidersAreAskedWhenTheCallerSaysSo(): void
    {
        $trading = $this->trading(live: false);

        self::assertSame(1.25, $trading->getLatest('EUR', 'USD', ['use_swap' => true])->getValue());
        self::assertCount(1, $this->requests);
    }

    public function testLiveTheStoredRatesAnswerWhenTheCallerSaysSo(): void
    {
        $trading = $this->trading();

        self::assertSame(1.10, $trading->getLatest('EUR', 'USD', ['use_swap' => false])->getValue());
        self::assertSame([], $this->requests);

        $trading->setLive(false);
        self::assertSame(1.10, $trading->getLatest('EUR', 'USD')->getValue());
        self::assertSame([], $this->requests);
    }

    public function testTheFirstRateFetchedIsCachedAnHourByDefault(): void
    {
        $trading = $this->trading();

        $trading->getLatest('EUR', 'USD', ['use_swap' => true]);

        self::assertSame([3600], $this->cache->ttls);
    }

    public function testACacheLifetimeSetBeforehandIsKept(): void
    {
        $trading = $this->trading();
        $trading->setCacheTTL(86400);

        $trading->getLatest('EUR', 'USD', ['use_swap' => true]);
        $trading->getLatest('EUR', 'USD', ['use_swap' => true]);

        self::assertSame([86400], $this->cache->ttls);
        // The second call was answered by the cache.
        self::assertCount(1, $this->requests);
    }
}

/** Swap's cache in memory, with the lifetime each rate was stored for. */
final class RecordingCache implements CacheInterface
{
    private array $items = [];

    /** @var list<int|\DateInterval|null> */
    public array $ttls = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->items[$key] = $value;
        $this->ttls[] = $ttl;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->items[$key]);
    }
}

/** Trading keeps one provider per class: one class each. */
abstract class ProviderStub implements CurrencyApiInterface
{
    public function __construct(private readonly int $priority, private readonly ?string $key = 'key')
    {
    }

    public function supports(string $key): bool
    {
        return $key === static::class;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getOptions(): array
    {
        return [];
    }

    public function getKey(): ?string
    {
        return $this->key;
    }
}

final class FixerStub extends ProviderStub
{
}

final class CentralBankStub extends ProviderStub
{
}

final class CurrencyLayerStub extends ProviderStub
{
}

/** Fixer's free plan, as Swap builds it: a name and an access key. */
final class FixerApiStub extends ProviderStub
{
    public static function getName(): string
    {
        return 'fixer';
    }

    public function getOptions(): array
    {
        return ['access_key' => 'key'];
    }
}
