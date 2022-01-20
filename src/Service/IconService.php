<?php

namespace Base\Service;

use Base\Model\IconizeInterface;
use Base\Model\IconProviderInterface;

class IconService
{
    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    protected $providers = [];
    public function getProviders() { return $this->providers; }
    public function getProvider(string $idOrClass): ?IconProviderInterface 
    {
        if(class_exists($idOrClass))
            return $this->providers[$idOrClass] ?? null;

        foreach($this->providers as $provider) {

            if ($provider->supports($idOrClass))
                return $provider;
        }

        return null;
    }

    public function addProvider(IconProviderInterface $provider): self
    {
        $this->providers[get_class($provider)] = $provider;
        return $this;
    }

    public function removeProvider(IconProviderInterface $provider): self
    {
        array_values_remove($this->providers, $provider);
        return $this;
    }

    public function iconify(null|string|array|IconizeInterface $icon, array $attributes = []) : null|string|array
    {
        if(!$icon) return $icon;

        $icon = $icon instanceof IconizeInterface ? $icon->__iconize() : $icon;
        if(is_array($icon)) 
            return array_map(fn($i) => $this->iconify($i, $attributes), $icon);

        foreach($this->providers as $provider) {

            if ($provider->supports($icon))
                return $provider->iconify($icon, $attributes);
        }

        return $this->imageService->imagify($icon, $attributes);
    }
}
