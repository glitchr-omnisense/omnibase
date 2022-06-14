<?php

namespace Base\Routing;

use Base\Routing\Generator\AdvancedUrlGenerator;
use Base\Routing\Matcher\AdvancedUrlMatcher;
use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBag;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;
use Symfony\Contracts\Cache\CacheInterface;

class AdvancedRouter implements AdvancedRouterInterface
{
    protected $router;

    public function __construct(Router $router, RequestStack $requestStack, ParameterBagInterface $parameterBag, SettingBag $settingBag, LocaleProviderInterface $localeProvider, CacheInterface $cache, string $debug)
    {
        $this->debug  = $debug;

        $this->router = $router;
        $this->router->setOption("matcher_class", AdvancedUrlMatcher::class);
        $this->router->setOption("generator_class", AdvancedUrlGenerator::class);

        $this->requestStack = $requestStack;
        $this->settingBag   = $settingBag;
        $this->parameterBag = $parameterBag;
        $this->localeProvider = $localeProvider;

        $this->cache             = !is_cli() && $parameterBag->get("base.router.use_cache") ? $cache : null;
        $this->cacheName         = "router." . hash('md5', self::class);
        $this->cacheRoutes       = $cache ? $cache->getItem($this->cacheName.".routes") : null;
        $this->cacheRouteMatches = $cache ? $cache->getItem($this->cacheName.".route_matches" ) : null;
        $this->cacheRouteGroups  = $cache ? $cache->getItem($this->cacheName.".route_groups" ) : null;

        $this->useCustomRouter = $parameterBag->get("base.router.use_custom_engine");
        $this->keepMachine     = $parameterBag->get("base.router.shorten.keep_machine");
        $this->keepSubdomain   = $parameterBag->get("base.router.shorten.keep_subdomain");
    }

    public function getCache() { return $this->cache; }
    public function getCacheRoutes() { return $this->cacheRoutes; }

    public function warmUp(string $cacheDir): array
    {
        if(getenv("SHELL_VERBOSITY") > 0 && php_sapi_name() == "cli") echo " // Warming up cache... Advanced router".PHP_EOL.PHP_EOL;
        $this->getRouteCollection()->all(); // Make sure it is once called

        return method_exists($this->router, "warmUp") ? $this->router->warmUp($cacheDir) : [];
    }

    public function getLang(?string $locale = null):string { return $this->localeProvider->getLang($locale); }
    public function isBackOffice(mixed $request = null) { return $this->isEasyAdmin($request) || $this->isProfiler($request); }
    public function isProfiler(mixed $request = null)
    {
        if(!$request) $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();
        else if(!$request instanceof Request)
            return false;

        $route = $this->getRouteName();
        return $route == "_wdt" || $route == "_profiler";
    }

    public function isWdt(mixed $request = null)
    {
        if(!$request) $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();
        else if(!$request instanceof Request)
            return false;

        $route = $this->getRouteName();
        return $route == "_wdt";
    }

    public function isEasyAdmin(mixed $request = null)
    {
        if(!$request) $request = $this->requestStack->getCurrentRequest();
        if($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();
        else if(!$request instanceof Request)
            return false;

        $controllerAttribute = $request->attributes->get("_controller");
        $array = is_array($controllerAttribute) ? $controllerAttribute : explode("::", $request->attributes->get("_controller") ?? "");
        $controller = explode("::", $array[0] ?? "")[0];

        $parents = [];

        $parent = $controller;
        while(class_exists($parent) && ( $parent = get_parent_class($parent)))
            $parents[] = $parent;

        $eaParents = array_filter($parents, fn($c) => str_starts_with($c, "EasyCorp\Bundle\EasyAdminBundle"));
        return !empty($eaParents);
    }

    public function keepMachine(): bool { return $this->keepMachine; }
    public function keepSubdomain(): bool { return $this->keepSubdomain; }

    public function getContext(): RequestContext { return $this->router->getContext(); }
    public function setContext(RequestContext $context) { $this->router->setContext($context); }

    public function getGenerator(): UrlGeneratorInterface { return  $this->router->getGenerator(); }
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string { return $this->router->generate($name, $parameters, $referenceType); }
    public function format(string $url): string
    {
        if($url === null) $url = get_url();

        $generator = $this->router->getGenerator();
        if($generator instanceof AdvancedUrlGenerator)
            return $generator->format($url);

        return $url;
    }

    public function match(string $pathinfo)       : array { return $this->router->match($pathinfo); }
    public function matchRequest(Request $request): array { return $this->router->matchRequest($request); }
    public function getMatcher(): UrlMatcherInterface { return  $this->router->getMatcher(); }

    public function getRouteCollection(): RouteCollection { return $this->router->getRouteCollection(); }

    public function getRequestUri(): ?string { return $this->getRequest() ? $this->getRequest()->getRequestUri() : $_SERVER["REQUEST_URI"] ?? null; }
    public function getRequest(): ?Request { return $this->requestStack ? $this->requestStack->getCurrentRequest() : null; }

    //
    // NB: Don't get confused, here. This route is not same annotation and...
    // ... is computed based on compiled routes from generator (not matcher)
    protected array $routes = [];
    public function getRoute(?string $routeNameOrUrl = null): ?Route
    {
        if ($routeNameOrUrl === null)
            $routeNameOrUrl = $this->getRequestUri();

        $routeName = $this->getRouteName($routeNameOrUrl);
        if(array_key_exists($routeName, $this->routes))
            return $this->routes[$routeName];

        $generator = $this->getGenerator();
        $matcher   = $this->getMatcher();
        $lang      = $this->localeProvider->getLang();

        $compiledRoutes = $generator->getCompiledRoutes();
        $compiledRoute = $compiledRoutes[$routeName] ?? $compiledRoutes[$routeName.".".$lang] ?? null;
        if($compiledRoute !== null) {

            $args = array_transforms(fn($k, $v): array => [$k, in_array($k, [3,4]) ? $matcher->path($v) : $v], $compiledRoute);
            $locale = $args[1]["_locale"] ?? null;
            $locale = $locale ? ["_locale" => $locale] : [];

            $this->routes[$routeName] = new Route(
                $args[3],
                array_intersect_key($args[1], array_flip($args[0])),
                array_merge($locale, $args[2]), [],
                $args[4], $args[5], $args[6]
            );

            return $this->routes[$routeName];
        }

        return null;
    }

    public function getRouteName(?string $routeUrl = null): ?string
    {
        if($this->getRequestUri() && !$routeUrl) return $this->getRouteName($this->getRequestUri());
        if($routeUrl && !str_contains($routeUrl, "/")) return $routeUrl;

        $routeMatch = $this->getRouteMatch($routeUrl);
        return $routeMatch ? $routeMatch["_route"] : null;
    }

    public function getRouteGroups(string $routeNameOrUrl): array
    {
        $routeName = $this->getRouteName($routeNameOrUrl);

        $matcher = $this->router->getMatcher();
        if ($matcher instanceof AdvancedUrlMatcher)
           return $matcher->groups($routeName);

        return $routeName ? [$routeName] : [];
    }

    public function getRouteMatch(?string $routeUrl = null): ?array
    {
        if($routeUrl === null) $routeUrl = $this->getRequestUri();

        try { return $this->match($routeUrl); }
        catch (ResourceNotFoundException $e) { return null; }
    }

    public function getRouteHash(string $routeNameOrUrl): string
    {
        return $routeNameOrUrl . ";" . serialize($this->getContext()) . ";" . $this->localeProvider->getLang();
    }
}
