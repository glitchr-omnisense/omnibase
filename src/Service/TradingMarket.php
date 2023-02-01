<?php

namespace Base\Service;

use Base\Cache\Abstract\AbstractLocalCache;
use Base\Cache\SimpleCache;
use Base\Cache\SimpleCacheInterface;
use Base\Service\Model\Currency\CurrencyApiInterface;
use Exchanger\Contract\CurrencyPair as CurrencyPairContract;
use Exchanger\Contract\ExchangeRate;
use Exchanger\CurrencyPair;
use Psr\SimpleCache\CacheInterface;
use Swap\Builder;
use Swap\Swap;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TradingMarket implements TradingMarketInterface
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

    public function __construct(SimpleCacheInterface $simpleCache, HttpClientInterface $httpClient)
    {
        $this->httpClient = new Psr18Client($httpClient);
        $this->simpleCache = new Psr16Cache(new FilesystemAdapter("swap"));
    }

    public function warmUp(string $cacheDir) { }

    protected $providers = [];
    public function getProviders() { return $this->providers; }
    public function getProvider(string $idOrClass): ?CurrencyApiInterface
    {
        if(class_exists($idOrClass))
            return $this->providers[$idOrClass] ?? null;

        foreach($this->providers as $provider) {

            if ($provider->supports($idOrClass))
                return $provider;
        }

        return null;
    }

    protected ?string $renderedCurrency = null;
    public function getRenderedCurrency(): ?string { return $this->renderedCurrency; }
    public function setRenderedCurrency(?string $renderedCurrency)
    {
        $this->renderedCurrency = $renderedCurrency;
        return $this;
    }

    public function normalize(string $source, string $target, mixed $value, null|string|int|\DateTime $datetime): ?ExchangeRate
    {
        if($value instanceof \Exchanger\ExchangeRate) return $value;

        return new \Exchanger\ExchangeRate(
            new CurrencyPair($source, $target), $value,
            cast_datetime($datetime), "local"
        );
    }

    protected $fallbacks = [];
    public function getFallback(string $source, ?string $target = null): ExchangeRate|array|null
    {
        if($target === null)
            return array_transforms(fn($k,$v):array => [explode("/", $k)[1],$v], array_key_startsWith($this->fallbacks, $source."/"));

        return $this->fallbacks[$source."/".$target] ?? null;
    }

    public function addFallback(string $source, string $target, mixed $value, null|string|int|\DateTime $date = "now"): self
    {
        $this->fallbacks[$source."/".$target] = $this->normalize($source, $target, $value, cast_datetime($date));
        $this->fallbacks[$target."/".$source] = $this->normalize($target, $source,1./$value, cast_datetime($date));
        return $this;
    }

    public function removeFallback(string $source, string $target): self
    {
        array_key_removes($this->fallbacks, $source."/".$target, $target."/".$source);
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
        if($this->swap) return true;

        $providers = $this->getProviders();
        if(count($providers) == 0) return false;

        $builder = new Builder();
        $builder->useSimpleCache($this->simpleCache);
        $builder->useHttpClient($this->httpClient);

        foreach($providers as $provider)
            $builder->add($provider->getName(), $provider->getOptions());

        if(!array_key_exists("", $this->options))
            $this->setCacheTTL(3600);

        $this->swap = $builder->build();
        return $this->swap !== null;
    }

    protected array $options = [];
    public function setCacheTTL(int $ttl)
    {
        $this->options["cache"] = ($this->simpleCache instanceof CacheInterface);
        $this->options["cache_ttl"] = $ttl;
    }

    public function getLatest(string $from, string $to, array $options = []): ?ExchangeRate
    {
        if(!$this->build()) return null;
        $options = array_merge($this->options, $options);
        $options["use_swap"] ??= true;

        if(!$options["use_swap"]) return $this->getFallback($from, $to);

        try { return $this->swap?->latest($from.'/'.$to, $options); }
        catch (\Exchanger\Exception\ChainException $e) { return $this->getFallback($from, $to); }
    }

    public function get(string $from, string $to, array $options = [], string $timeAgo = "now"): ?ExchangeRate
    {
        if(empty($timeAgo) || $timeAgo == "now")
            return $this->getLatest($from, $to, $options);

        if(!$this->build()) return null;
        $options = array_merge($this->options, $options);
        $options["use_swap"] ??= true;

        if(!$options["use_swap"]) return null;

        $timeAgo = (str_starts_with($timeAgo, "-") ? '' : '-') . $timeAgo;
        try { return $this->swap?->historical($from.'/'.$to, (new \DateTime())->modify($timeAgo), $options); }
        catch (\Exchanger\Exception\ChainException $e) { return null; }
    }

    public function convert(string|float $cash, string $source, string $target, array $options = [], string $timeAgo = "now"): ?float
    {
        if(!is_string($cash)) $cash = (string) $cash;

        $rate = $this->get($source, $target, $options, $timeAgo)->getValue();
        return $rate === null ? null : $cash * $rate;
    }

    public function convertLatest(string|float $cash, string $source, string $target, array $options = []): ?float
    {
        if(!is_string($cash)) $cash = (string) $cash;

        $rate = $this->getLatest($source, $target, $options)->getValue();
        return $rate === null ? null : $cash * $rate;
    }

    public function convertFallback(string|float $cash, string $source, string $target): ?float
    {
        if(!is_string($cash)) $cash = (string) $cash;

        $rate = $this->getFallback($source, $target)->getValue();
        return $rate === null ? null : $cash * $rate;
    }
}