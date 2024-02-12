<?php

namespace Base\Service;

use Base\Annotations\Annotation\Iconize;
use Base\Annotations\AnnotationReader;
use Base\Cache\Abstract\AbstractLocalCache;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use Base\Routing\RouterInterface;
use ErrorException;

/**
 *
 */
class IconProvider extends AbstractLocalCache
{
    /**
     * @var AnnotationReader
     */
    protected AnnotationReader $annotationReader;
    /**
     * @var MediaServiceInterface
     */
    protected MediaServiceInterface $mediaService;
    /**
     * @var LocalizerInterface
     */
    protected LocalizerInterface $localizer;
    /**
     * @var RouterInterface
     */
    protected RouterInterface $router;

    public function __construct(AnnotationReader $annotationReader, MediaServiceInterface $mediaService, LocalizerInterface $localizer, RouterInterface $router, string $cacheDir, ?string $buildDir = null)
    {
        $this->annotationReader = $annotationReader;
        $this->mediaService = $mediaService;
        $this->localizer = $localizer;
        $this->router = $router;

        parent::__construct($cacheDir, $buildDir);
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): bool
    {
        $this->routeIcons = $this->getCache("/RouteIcons", function () {
            return array_transforms(function ($route, $controller): ?array {
                $controller = $controller->getDefault("_controller");
                if (!$controller) {
                    return null;
                }

                try {
                    list($class, $method) = explode("::", $controller);
                } catch (ErrorException $e) {
                    return null;
                }
                if (!class_exists($class)) {
                    return null;
                }

                $iconAnnotations = $this->annotationReader->getMethodAnnotations($class, [Iconize::class])[$method] ?? [];
                if (!$iconAnnotations) {
                    return null;
                }

                return [$route, end($iconAnnotations)->getIcons()];
            }, $this->router->getRouteCollection()->all());
        });

        return true;
    }

    protected ?array $routeIcons = null;

    /**
     * @param string|null $route
     * @return array|mixed|null
     */
    public function getRouteIcons(?string $route = null)
    {
        if ($this->routeIcons && $route === null) {
            return $this->routeIcons;
        }

        return $this->routeIcons[$route . "." . $this->localizer->getLocaleLang()]
            ?? $this->routeIcons[$route]

            ?? $this->routeIcons[$route . ".default." . $this->localizer->getLocaleLang()]
            ?? $this->routeIcons[$route . ".default"]

            ?? $this->routeIcons[$route . "." . $this->localizer->getDefaultLocaleLang()]
            ?? $this->routeIcons[$route . ".default." . $this->localizer->getDefaultLocaleLang()]
            ?? null;
    }

    protected array $adapters = [];

    /**
     * @return array
     */
    public function getAdapters()
    {
        return $this->adapters;
    }

    public function getAdapter(string $idOrClass): ?IconAdapterInterface
    {
        if (class_exists($idOrClass)) {
            return $this->adapters[$idOrClass] ?? null;
        }

        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($idOrClass)) {
                return $adapter;
            }
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

    public function iconify(null|string|array|EnumType|IconizeInterface $icon, array $attributes = [], bool $wrap = true): null|string|array
    {
        if (!$icon) {
            return $icon;
        }

        if ($icon instanceof IconizeInterface) {
            $icon = $icon->__iconize() ?? $icon->__iconizeStatic();
        } elseif (($routeIcons = $this->getRouteIcons($icon))) {
            $icon = $routeIcons;
        }

        if (is_array($icon) && is_associative($icon)) {
            return array_map_recursive(fn($i) => $this->iconify($i, $attributes, false), $icon);
        }
        if (is_array($icon)) {
            $icon = array_filter(array_map(fn($i) => $this->iconify($i, $attributes, $wrap), $icon));
            if ($icon) {
                return array_merge(...$icon);
            }
        } else {
            foreach ($this->adapters as $provider) {
                if ($provider->supports($icon)) {
                    $icon = $provider->iconify($icon, $attributes, $wrap);
                    return $wrap ? [$icon] : $icon;
                }
            }
        }

        if($icon == "fa-solid fa-question-circle") return [];
        return $this->iconify("fa-solid fa-question-circle", $attributes, $wrap) ?? null;
    }
}
