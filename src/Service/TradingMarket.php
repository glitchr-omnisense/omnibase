<?php

namespace Base\Service;

use Base\Service\Model\Currency\CurrencyApiInterface;
use Exchanger\Contract\ExchangeRate;
use Swap\Builder;
use Swap\Swap;

class TradingMarket implements TradingMarketInterface
{
    /**
     * @var Swap
     */
    protected $swap = null;

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

    protected ?string $defaultCurrency = null;
    public function getDefaultCurrency(): ?string { return $this->defaultCurrency; }
    public function setDefaultCurrency(string $defaultCurrency)
    {
        $this->defaultCurrency = $defaultCurrency;
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
        foreach($providers as $provider)
            $builder->add($provider->getName(), $provider->getOptions());


        $this->swap = $builder->build();
        return $this->swap !== null;
    }

    protected array $options = [];
    public function setCacheTTL(int $ttl)
    {
        $this->options["cache"] = true;
        $this->options["cache_ttl"] = $ttl;
    }

    public function getLatest(string $from, string $to, array $options = []): ?ExchangeRate
    {
        if(!$this->build()) return null;

        return $this->swap?->latest(
            $from.'/'.$to,
            array_merge($this->options, $options)
        );
    }

    public function get(string $from, string $to, string $timeAgo = "now"): ?ExchangeRate
    {
        if(!$this->build()) return null;

        $timeAgo = (str_starts_with($timeAgo, "-") ? '' : '-') . $timeAgo;
        $this->swap?->historical(
            $from.'/'.$to,
            (new \DateTime())->modify($timeAgo),
            array_merge($this->options, $options)
        );
    }

    public function convert(string|float $cash, string $source, string $target): float
    {
        if(!is_string($cash)) $cash = (string) $cash;
        return $cash * $this->getLatest($source, $target)->getValue();
    }
}