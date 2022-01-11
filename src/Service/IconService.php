<?php

namespace Base\Service;

class IconService
{
    public function __construct() {}

    protected $providers = [];
    public function addProvider(IconProviderInterface $provider): self
    {
        $this->providers[] = $provider;
        return $this;
    }

    public function removeProvider(IconProviderInterface $provider): self
    {
        array_values_remove($this->providers, $provider);
        return $this;
    }

    public function iconify(null|string|array $icon, array $attributes = []) : ?string
    {
        if(!$icon) return $icon;

        if(is_array($icon)) 
            return array_map(fn($i) => $this->iconify($i, $attributes), $icon);

        foreach($this->providers as $provider) {

            if ($provider->supports($icon))
                return $provider->iconify($icon, $attributes);
        }

        return null;
    }
}
