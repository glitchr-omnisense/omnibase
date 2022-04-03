<?php

namespace Base\Routing;

use Base\Service\BaseSettings;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;

use Symfony\Component\Routing\RouterInterface;

class AdvancedRouter implements AdvancedRouterInterface
{
    protected $router;

    public function warmUp(string $cacheDir) { return $this->router->warmUp(); }

    public function __construct(RouterInterface $router, RequestStack $requestStack, BaseSettings $baseSettings)
    {
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->baseSettings = $baseSettings;
    }

    public function getRouteCollection() { return $this->router->getRouteCollection(); }
    public function getContext(): RequestContext { return $this->router->getContext(); }
    public function setContext(RequestContext $context) { $this->router->setContext($context); }
    public function match(string $pathinfo): array { return $this->router->match($pathinfo); }
    public function matchRequest(Request $request): array { return $this->router->matchRequest($request); }

    public function getAsset(string $url): string
    {
        $url = trim($url);
        $parseUrl = parse_url($url);
        if($parseUrl["scheme"] ?? false)
            return $url;

        $request = $this->requestStack->getCurrentRequest();
        $baseDir = $request ? $request->getBasePath() : $_SERVER["CONTEXT_PREFIX"] ?? "";

        $path = trim($parseUrl["path"]);
        if($path == "/") return $baseDir;
        else if(!str_starts_with($path, "/"))
            $path = $baseDir."/".$path;

        return $path;
    }

    public function getRequest(): ?Request { return $this->getCurrentRequest(); }
    public function getCurrentRequest(): ?Request { return $this->requestStack ? $this->requestStack->getCurrentRequest() : null; }
    public function isProfiler($request = null)
    {
        if(!$request) $request = $this->getRequest();
        if($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();
        else if(!$request instanceof Request)
            throw new \InvalidArgumentException("Invalid argument provided, expected either RequestStack or Request");

        $route = $request->get('_route');

        return $route == "_wdt" || $route == "_profiler";
    }

    public function isEasyAdmin($request = null)
    {
        if(!$request) $request = $this->getRequest();
        if($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();
        else if(!$request instanceof Request)
            throw new \InvalidArgumentException("Invalid argument provided, expected either RequestStack or Request");

        $controllerAttribute = $request->attributes->get("_controller");
        $array = is_array($controllerAttribute) ? $controllerAttribute : explode("::", $request->attributes->get("_controller"));
        $controller = explode("::", $array[0])[0];

        $parents = [];

        $parent = $controller;
        while(class_exists($parent) && ( $parent = get_parent_class($parent)))
            $parents[] = $parent;

        $eaParents = array_filter($parents, fn($c) => str_starts_with($c, "EasyCorp\Bundle\EasyAdminBundle"));
        return !empty($eaParents);
    }

    public function generateUrl(string $route = "", array $routeParameters = []): ?string { return $this->getUrl($route, $routeParameters); }
    public function getCurrentUrl(): ?string { return $this->getUrl(); }
    public function getUrl(?string $route = null, array $routeParameters = []): ?string
    {
        if (!empty($route)) {

            try { return $this->router->generate($route, $routeParameters); }
            catch (RouteNotFoundException $e) { return null; }
        }

        $request = $this->getRequest();
        if(!$request) return null;

        $route = $request->get('_route');
        return $route ? $this->router->generate($route) : null;
    }

    public function getRouteArray(?string $url): ?array
    {
        if(!$url) return null;

        $baseDir = $this->getAsset("/");
        $path = parse_url($url, PHP_URL_PATH);
        if ($baseDir && strpos($path, $baseDir) === 0)
            $path = mb_substr($path, strlen($baseDir));

        try { return $this->router->match($path); }
        catch (ResourceNotFoundException $e) { return null; }
    }

    public function getRoute(?string $url): ?Route 
    { 
        $routeArray = $this->getRouteArray($url);
        if(!$routeArray) return null;

        $routeName = $routeArray["_route"];
        if(array_key_exists("_locale", $routeArray)) 
            $routeName .= ".".$routeArray["_locale"];
        
        return $this->router->getRouteCollection()->get($routeName);
    }

    public function getRouteName(?string $url): ?string
    {
        $routeArray = $this->getRouteArray($url);
        return $routeArray ? $routeArray["_route"] : null;
    }

    public function getRouteParameters(?string $url): array
    {
        $route = $this->getRoute($url);
        return $route ? $route->getDefaults() : [];
    }

    public function getCurrentRoute(): ?Route 
    {
        $request = $this->getRequest();
        return $request ? $this->getRoute($request->getRequestUri()) : null;
    }

    public function getCurrentRouteName(): ?string
    {
        $request = $this->getRequest();
        return $request ? $this->getRouteName($request->getRequestUri()) : null;
    }

    public function getCurrentRouteParameters(): array
    {
        $request = $this->getRequest();
        return $request ? $this->getRouteParameters($request->getRequestUri()) : null;
    }


    // NB: dump(); seems not to be working in here..
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        // Symfony internal root, I assume.. Infinite loop due to "_profiler*" route, if not set
        if(str_starts_with($name, "_")) {
        
            try { return $this->router->generate($name, $parameters, $referenceType); }
            catch (Exception $e) { return null; }
        }

        // Handle CLI case using either $_SERVER variables,
        // or base settting database information, if available.
        $baseDir = null;
        if(is_cli()) {
               
            switch($referenceType) {

                case self::ABSOLUTE_URL:
                case self::NETWORK_PATH: // NOT IMPLEMENTED for cli :()
                    break;

                case self::RELATIVE_PATH:
                case self::ABSOLUTE_PATH:

                    $baseDir    = $_SERVER['BASE']        ?? $_SERVER["CONTEXT_PREFIX"] ?? $this->baseSettings->base_dir();
                    $baseDir    = "/".trim($baseDir, "/");
            }
        }

        // Implement route subgroup to improve connectivity
        // between logical routes in case of multiple @Route annotations
        $routeName = explode(".", $name);
        $currentRouteName = explode(".", $this->getCurrentRouteName());

        $name = $routeName[0];
        $group = count($routeName) > 1 ? tail($routeName) : null;
        $group = $group ?? count($currentRouteName) > 1 ? tail($currentRouteName) : null;
        $group = $group ?? [];
        $group = $group ? ".".implode(".",$group) : null;

        //
        // Strip unused variables from main group
        $url = $this->getUrl($name, $parameters);
        $groupUrl = $this->getUrl($name.$group, $parameters);

        if($groupUrl !== null && $url !== null) {

            $keys = array_keys(array_diff_key($this->getRouteParameters($url), $this->getRouteParameters($groupUrl)));
            $parameters = array_key_removes($parameters, ...$keys);
        }
        
        // Try to compute subgroup (or base one)
        try { $url = $baseDir . $this->router->generate($name.$group, $parameters, $referenceType); }
        catch (Exception $e) { $url = $baseDir . $this->router->generate($name, $parameters, $referenceType); }

        // Clean up double slashes..
        $parts = parse_url($url);
        $parts["path"] = str_rstrip(str_replace("//", "/", $parts["path"]), "/");
        $url = build_url($parts);

        return $url;
    }
}