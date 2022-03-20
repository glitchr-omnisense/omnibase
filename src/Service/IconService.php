<?php

namespace Base\Service;

use Base\Annotations\Annotation\Iconize;
use Base\Annotations\AnnotationReader;
use Base\Model\IconizeInterface;
use Base\Model\IconProviderInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;

class IconService
{
    protected $routeIcons = [];
    public function getRouteIcons(string $route) { return $this->routeIcons[$route] ?? null; }

    public function __construct(AnnotationReader $annotationReader, ImageService $imageService, CacheInterface $cache, RouterInterface $router)
    {
        $this->imageService = $imageService;

        // Turn icon annotation into cache
        $cacheName = "base.icon_service." . hash('md5', self::class);
        $cacheRouteIcons = $cache->getItem($cacheName."route_icons");

        $this->routeIcons = $cacheRouteIcons->get();
        if($this->routeIcons === null) {

            $this->routeIcons = array_transforms(function($route, $controller) use ($annotationReader) : ?array {

                $controller = $controller->getDefault("_controller");
                if(!$controller) return null;

                list($class, $method) = explode("::", $controller);
                if(!class_exists($class)) return null;

                $iconAnnotations = $annotationReader->getMethodAnnotations($class, [Iconize::class])[$method] ?? [];
                if(!$iconAnnotations) return null;

                return [$route, end($iconAnnotations)->getIcons()];

            }, $router->getRouteCollection()->all());

            if(!is_cli()) $cache->save($cacheRouteIcons->set($this->routeIcons));
        }
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

        $icon = $icon instanceof IconizeInterface ? $icon->__iconize() : $this->getRouteIcons($icon) ?? $icon;
        if(is_array($icon))
            return array_map(fn($i) => $this->iconify($i, $attributes), $icon);

            foreach($this->providers as $provider) {
            
                if ($provider->supports($icon))
                    return $provider->iconify($icon, $attributes);
        }

        return $this->imageService->imagify($icon, $attributes);
    }
}
