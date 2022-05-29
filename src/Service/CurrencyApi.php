<?php

namespace Base\Service;

use Base\Model\CurrencyApiInterface;
use Swap\Builder;

class CurrencyApi
{
    public function __construct(array $options)
    {
    }

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

    protected function build()
    {
        $this->swap = new Builder();

        foreach($this->getProviders() as $provider)
            $this->swap->add($provider->getName(), $provider->getOptions());

        return $this->swap->build();
    }

    public function getRate   (string $from, string $to) { return $swap->latest($from.'/'.$to); }
    public function getHistory(string $from, string $to, string $timeAgo = "15 days")
    {
        $timeAgo = (str_starts_with($timeAgo, "-") ? '-' : '') . $timeAgo;
        $swap->historical($from.'/'.$to, (new \DateTime())->modify($timeAgo));
    }
}