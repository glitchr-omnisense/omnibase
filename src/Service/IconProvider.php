<?php

namespace Base\Service;

use Base\Annotations\Annotation\Iconize;
use Base\Annotations\AnnotationReader;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use Base\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;

class IconProvider
{
    protected $routeIcons = [];

    public function getRouteIcons(string $route)
    {
        return $this->routeIcons[$route.".".$this->localeProvider->getLang()] 
            ?? $this->routeIcons[$route]

            ?? $this->routeIcons[$route.".default.".$this->localeProvider->getLang()] 
            ?? $this->routeIcons[$route.".default"]

            ?? $this->routeIcons[$route.".".$this->localeProvider->getDefaultLang()]
            ?? $this->routeIcons[$route.".default.".$this->localeProvider->getDefaultLang()]
            ?? null;
    }

    public function __construct(AnnotationReader $annotationReader, ImageService $imageService, CacheInterface $cache, LocaleProviderInterface $localeProvider, RouterInterface $router)
    {
        $this->imageService = $imageService;
        $this->localeProvider = $localeProvider;

        // Turn icon annotation into cache
        $cacheName = "base.icon_service." . hash('md5', self::class);
        $cacheRouteIcons = !is_cli() ? $cache->getItem($cacheName.".route_icons") : null;

        $this->routeIcons = $cacheRouteIcons !== null ? $cacheRouteIcons->get() : [];
        if($this->routeIcons === null) {

            $this->routeIcons = array_transforms(function($route, $controller) use ($annotationReader, $router) : ?array {

                $controller = $controller->getDefault("_controller");
                if(!$controller) return null;

                try { list($class, $method) = explode("::", $controller); }
                catch(\ErrorException $e) { return null; } 
                if(!class_exists($class)) return null;

                $iconAnnotations = $annotationReader->getMethodAnnotations($class, [Iconize::class])[$method] ?? [];
                if(!$iconAnnotations) return null;

                return [$route, end($iconAnnotations)->getIcons()];

            }, $router->getRouteCollection()->all());

            if ($cacheRouteIcons !== null)
                $cache->save($cacheRouteIcons->set($this->routeIcons));
        }
    }

    protected $adapters = [];
    public function getAdapters() { return $this->adapters; }
    public function getAdapter(string $idOrClass): ?IconAdapterInterface
    {
        if(class_exists($idOrClass))
            return $this->adapters[$idOrClass] ?? null;

        foreach($this->adapters as $adapter) {

            if ($adapter->supports($idOrClass))
                return $adapter;
        }

        return null;
    }

    public function addAdapter(IconAdapterInterface $provider): self
    {
        $this->adapters[get_class($provider)] = $provider;
        return $this;
    }

    public function removeAdapter(IconAdapterInterface $provider): self
    {
        array_values_remove($this->adapters, $provider);
        return $this;
    }

    public function iconify(null|string|array|IconizeInterface $icon, array $attributes = []) : null|string|array
    {
        if(!$icon) return $icon;

        if($icon instanceof IconizeInterface)
            $icon = $icon->__iconize() ?? $icon->__iconizeStatic();
        else if(($routeIcons = $this->getRouteIcons($icon)))
            $icon = $routeIcons;

        if(is_array($icon)) 
            return array_merge(...array_map(fn($i) => $this->iconify($i, $attributes), $icon));

        foreach($this->adapters as $provider) {

            if ($provider->supports($icon))
                return [$provider->iconify($icon, $attributes)];
        }

        return $this->iconify("fas fa-question-circle", $attributes) ?? null;
    }
}
