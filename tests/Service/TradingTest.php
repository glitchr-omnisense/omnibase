<?php

namespace Tests\Base\Service;

use Base\Service\Model\Currency\CurrencyApiInterface;
use Base\Service\Trading;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * The order Swap asks the currency providers in.
 *
 * TradingPass adds them in the container's order, which puts an application's
 * services before the bundles': a keyless fallback an application adds (a
 * central bank's reference rates, at a low priority) answered before the
 * keyed providers typed in the admin, whatever its priority said.
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
