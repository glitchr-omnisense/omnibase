<?php

namespace Base\Service;

use Base\Model\Sharer\SharerAdapterInterface;

class SocialSharer
{
    protected $adapters = [];
    public function getAdapters() { return $this->adapters; }
    public function getAdapter(string $idOrClass): ?SharerAdapterInterface
    {
        if(class_exists($idOrClass))
            return $this->adapters[$idOrClass] ?? null;

        foreach($this->adapters as $provider) {

            if ($provider->supports($idOrClass))
                return $provider;
        }

        return null;
    }

    public function addAdapter(SharerAdapterInterface $provider): self
    {
        $this->adapters[get_class($provider)] = $provider;
        return $this;
    }

    public function removeAdapter(SharerAdapterInterface $provider): self
    {
        array_values_remove($this->adapters, $provider);
        return $this;
    }

    // public function iconify(null|string|array|IconizeInterface $icon, array $attributes = []) : null|string|array
    // {
    //     if(!$icon) return $icon;

    //     $icon = $icon instanceof IconizeInterface ? $icon->__iconize() : $this->getRouteIcons($icon) ?? $icon;
    //     if(is_array($icon))
    //         return array_map(fn($i) => $this->iconify($i, $attributes), $icon);

    //         foreach($this->adapters as $provider) {
            
    //             if ($provider->supports($icon))
    //                 return $provider->iconify($icon, $attributes);
    //     }

    //     // return $this->imageService->imagify($icon, $attributes);
    // }
}
