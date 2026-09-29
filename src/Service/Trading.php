<?php

namespace Base\Service;

use Base\Cache\SimpleCacheInterface;
use Base\Service\Model\Currency\CurrencyApiInterface;
use DateTime;
use Exception;
use Exchanger\Contract\ExchangeRate;
use Exchanger\CurrencyPair;
use Exchanger\Exception\ChainException;
use Psr\SimpleCache\CacheInterface;
use Swap\Builder;
use Swap\Swap;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Trading implements TradingInterface
{
    /**
     * @var Swap
     */
    protected $swap = null;

    /**
     * @var HttpClientInterface
     */
    protected $httpClient;

    /**
     * @var SimpleCacheInterface
     */
    protected $simpleCache;

    /**
     * @param bool $live whether a call that does not say otherwise ("use_swap")
     *                   may ask the providers. On by default; an application on
     *                   a provider's free plan (a hundred requests a month,
     *                   which a visitor's page views would spend in a day) sets
     *                   BASE_TRADING_LIVE=0: the stored rates, laid in as
     *                   fallbacks, then answer, and whoever refreshes them (an
     *                   administrator's action, a command) passes
     *                   ['use_swap' => true].
     */
    public function __construct(HttpClientInterface $httpClient, string $cacheDir, protected bool $live = true)
    {
        $this->httpClient = new Psr18Client($httpClient);
        $this->simpleCache = new Psr16Cache(new FilesystemAdapter("swap", 0, $cacheDir));
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null)
    {
    }

    protected $providers = [];

    /**
     * The providers with a key, in the order Swap asks them: the highest
     * getPriority() first, as for Symfony's tagged services - the order they
     * were registered in only among equals. TradingPass adds them in the
     * container's order, which puts an application's before the bundles':
     * without this, a keyless fallback (a central bank's rates) would answer
     * before the keyed providers typed in the admin.
     *
     * @return array|mixed
     */
    public function getProviders()
    {
        $providers = array_filter($this->providers, fn($p) => $p->getKey() !== null);
        uasort($providers, fn($a, $b) => $b->getPriority() <=> $a->getPriority());

        return $providers;
    }

    public function getProvider(string $idOrClass): ?CurrencyApiInterface
    {
        if (class_exists($idOrClass)) {
            return $this->providers[$idOrClass] ?? null;
        }

        foreach ($this->providers as $provider) {
            if ($provider->supports($idOrClass)) {
                return $provider;
            }
        }

        return null;
    }

    protected ?string $renderedCurrency = null;

    public function getRenderedCurrency(): ?string
    {
        return $this->renderedCurrency;
    }

    /**
     * @param string|null $renderedCurrency
     * @return $this
     */
    public function setRenderedCurrency(?string $renderedCurrency)
    {
        $this->renderedCurrency = $renderedCurrency;
        return $this;
    }

    public function normalize(string $source, string $target, mixed $value, null|string|int|DateTime $datetime): ?ExchangeRate
    {
        if ($value instanceof \Exchanger\ExchangeRate) {
            return $value;
        }

        return new \Exchanger\ExchangeRate(
            new CurrencyPair($source, $target),
            $value,
            cast_datetime($datetime),
            "local"
        );
    }

    protected $fallbacks = [];

    public function getFallback(string $source, ?string $target = null): ExchangeRate|array|null
    {
        if ($target === null) {
            return array_transforms(fn($k, $v): array => [explode("/", $k)[1], $v], array_key_startsWith($this->fallbacks, $source . "/"));
        }

        return $this->fallbacks[$source . "/" . $target] ?? null;
    }

    public function addFallback(string $source, string $target, mixed $value, null|string|int|DateTime $date = "now"): self
    {
        $this->fallbacks[$source . "/" . $source] = $this->normalize($source, $source, 1.0, cast_datetime($date));
        $this->fallbacks[$target . "/" . $target] = $this->normalize($target, $target, 1.0, cast_datetime($date));

        $this->fallbacks[$source . "/" . $target] = $this->normalize($source, $target, $value, cast_datetime($date));
        $this->fallbacks[$target . "/" . $source] = $this->normalize($target, $source, 1. / $value, cast_datetime($date));

        return $this;
    }

    public function removeFallback(string $source, string $target): self
    {
        array_key_removes($this->fallbacks, $source . "/" . $target, $target . "/" . $source);
        return $this;
    }

    public function addProvider(CurrencyApiInterface $provider): self
    {
        $this->providers[get_class($provider)] = $provider;
        return $this;
    }

    public function removeProvider(CurrencyApiInterface $provider): self
    {
        array_values_remove($this->providers, $provider);
        return $this;
    }

    protected function build(): bool
    {
        if ($this->swap) {
            return true;
        }

        $providers = $this->getProviders();
        if (count($providers) == 0) {
            return false;
        }

        $builder = new Builder();
        $builder->useSimpleCache($this->simpleCache);
        $builder->useHttpClient($this->httpClient);

        foreach ($providers as $provider) {
            $builder->add($provider->getName(), $provider->getOptions());
        }

        // An hour unless setCacheTTL() already chose another lifetime.
        if (!array_key_exists("cache_ttl", $this->options)) {
            $this->setCacheTTL(3600);
        }

        $this->swap = $builder->build();
        return true;
    }

    protected array $options = [];

    public function setCacheTTL(int $ttl)
    {
        $this->options["cache"] = ($this->simpleCache instanceof CacheInterface);
        $this->options["cache_ttl"] = $ttl;
    }

    public function isLive(): bool
    {
        return $this->live;
    }

    public function setLive(bool $live): static
    {
        $this->live = $live;
        return $this;
    }

    /**
     * The options for Swap, or null when the providers are not to be asked:
     * offline, or none has a key. build() is only called when going online,
     * as reading the providers' keys reads the settings.
     */
    protected function swapOptions(array $options): ?array
    {
        $options["use_swap"] ??= $this->live;
        if (!$options["use_swap"] || !$this->build()) {
            return null;
        }

        // After build(), which sets the default cache lifetime.
        return array_merge($this->options, $options);
    }

    public function getLatest(string $source, string $target, array $options = []): ?ExchangeRate
    {
        $options = $this->swapOptions($options);
        if ($options === null) {
            return $this->getFallback($source, $target);
        }

        try {
            return $this->swap?->latest($source . '/' . $target, $options);
        } catch (ChainException $e) {
            return $this->getFallback($source, $target);
        }
    }

    public function get(string $from, string $to, array $options = [], string $timeAgo = "now"): ?ExchangeRate
    {
        if (empty($timeAgo) || $timeAgo == "now") {
            return $this->getLatest($from, $to, $options);
        }

        $options = $this->swapOptions($options);
        if ($options === null) {
            return null;
        }

        $timeAgo = (str_starts_with($timeAgo, "-") ? '' : '-') . $timeAgo;
        try {
            return $this->swap?->historical($from . '/' . $to, (new DateTime())->modify($timeAgo), $options);
        } catch (ChainException $e) {
            return null;
        }
    }

    public function convert(string|float $cash, string $source, string $target, array $options = [], string $timeAgo = "now"): ?float
    {
        if (!is_string($cash)) {
            $cash = (string)$cash;
        }

        if ($source == $target) {
            return $cash;
        }

        $rate = $this->get($source, $target, $options, $timeAgo)?->getValue();
        if ($rate == null) {
            throw new Exception("No source available for currency exchange.");
        }

        return $cash * $rate;
    }

    public function convertLatest(string|float $cash, string $source, string $target, array $options = []): ?float
    {
        if (!is_string($cash)) {
            $cash = (string)$cash;
        }

        $rate = $this->getLatest($source, $target, $options)->getValue();
        return $cash * $rate;
    }

    public function convertFallback(string|float $cash, string $source, string $target): ?float
    {
        if (!is_string($cash)) {
            $cash = (string)$cash;
        }

        $rate = $this->getFallback($source, $target)->getValue();
        return $cash * $rate;
    }
}
