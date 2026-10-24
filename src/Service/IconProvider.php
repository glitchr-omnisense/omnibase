<?php

namespace Base\Service;

use Base\Attributes\Attribute\Iconize;
use Base\Attributes\AttributeReader;
use Base\Cache\Abstract\AbstractLocalCache;

use Base\Database\Type\EnumType;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use Base\Routing\AdvancedRouterInterface;
use ErrorException;

class IconProvider extends AbstractLocalCache
{
    /**
     * @var AttributeReader
     */
    protected AttributeReader $attributeReader;
    /**
     * @var MediaServiceInterface
     */
    protected MediaServiceInterface $mediaService;
    /**
     * @var LocalizerInterface
     */
    protected LocalizerInterface $localizer;
    /**
     * @var AdvancedRouterInterface
     */
    protected AdvancedRouterInterface $router;

    public function __construct(AttributeReader $attributeReader, MediaServiceInterface $mediaService, LocalizerInterface $localizer, AdvancedRouterInterface $router, string $cacheDir, ?string $buildDir = null)
    {
        $this->attributeReader = $attributeReader;
        $this->mediaService = $mediaService;
        $this->localizer = $localizer;
        $this->router = $router;

        parent::__construct($cacheDir, $buildDir);
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->routeIcons = $this->getCache("/RouteIcons", function () {
            return array_transforms(function ($route, $controller): ?array {
                
                $controller = $controller->getDefault("_controller");
                if (!$controller) {
                    return [];
                }

                // routes may declare [Class::class, 'method'] instead of "Class::method"
                if (is_array($controller)) {
                    $controller = implode("::", $controller);
                }
                if (!is_string($controller)) {
                    return [];
                }

                // An invokable controller (an action, "Class") is its __invoke.
                $parts = explode("::", $controller, 2) + [1 => "__invoke"];

                list($class, $method) = $parts;
                if (!class_exists($class)) {
                    return [];
                }

                $iconAttributes = $this->attributeReader->getMethodAttributes($class, [Iconize::class])[$method] ?? [];
                if (!$iconAttributes) {
                    return [];
                }

                return [$route, end($iconAttributes)->getIcons()];

            }, $this->router->getRouteCollection()->all());
        });

        return [];
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

    /**
     * The icons' rule over this provider's adapters and the routes' icons it
     * reads (Icons, framework-free: what `|iconify` answers by).
     */
    public function icons(): Icons
    {
        return $this->icons ??= new Icons(fn (): array => $this->adapters, $this->getRouteIcons(...));
    }

    private ?Icons $icons = null;

    public function iconify(null|string|array|EnumType|IconizeInterface $icon, array $attributes = [], bool $wrap = true): null|string|array
    {
        return $this->icons()->iconify($icon, $attributes, $wrap);
    }
}
