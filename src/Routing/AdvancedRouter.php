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
    public function warmUp(string $cacheDir) 
    { 
        if(getenv("SHELL_VERBOSITY") > 0 && php_sapi_name() == "cli") echo " // Warming up cache... Advanced router".PHP_EOL.PHP_EOL;
        return method_exists($this->router, "warmUp") ? $this->router->warmUp($cacheDir) : $this->getRouteCollection();
    }

    public function getAsset(string $routeUrl): string
    {
        $routeUrl = trim($routeUrl);
        $parseUrl = parse_url($routeUrl);
        if($parseUrl["scheme"] ?? false)
            return $routeUrl;

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

    public function getRouteArray(?string $routeUrl): ?array
    {
        if(!$routeUrl) return null;

        $baseDir = $this->getAsset("/");
        $path = parse_url($routeUrl, PHP_URL_PATH);
        if ($baseDir && strpos($path, $baseDir) === 0)
            $path = mb_substr($path, strlen($baseDir));

        try { return $this->router->match($path); }
        catch (ResourceNotFoundException $e) { return null; }
    }

    public function getRoute(?string $routeUrl): ?Route 
    { 
        $routeArray = $this->getRouteArray($routeUrl);
        if(!$routeArray) return null;

        $routeName = $routeArray["_route"];
        if(array_key_exists("_group", $routeArray)) 
            $routeName .= ".".$routeArray["_group"];
        
        $routeName = $routeArray["_route"];
        if(array_key_exists("_locale", $routeArray)) 
            $routeName .= ".".$routeArray["_locale"];
            
        return $this->router->getRouteCollection()->get($routeName);
    }

    public function getRouteName(?string $routeUrl): ?string
    {
        $routeArray = $this->getRouteArray($routeUrl);
        return $routeArray ? $routeArray["_route"] : null;
    }

    public function getRouteGroups(?string $routeName): array
    {
        $routeName = explode(".", $routeName ?? "")[0];
        return array_unique(array_keys(array_transforms(

            function($k, $route) use ($routeName) :?array {

                if($k == $routeName) return ["", $route];
                if(str_starts_with($k.".", $routeName)) {

                    $kSplit = explode(".", $k);
                    $isLocalized = array_key_exists("_locale", $route->getDefaults());
                    if($isLocalized && count($kSplit) < 3) $k = "";
                    else $k = explode(".", $k)[1] ?? "";

                    return [$k, $route];
                }

                return null;

            }, $this->router->getRouteCollection()->all()
        )));
    }

    public function getRouteParameters(?string $routeUrl): array
    {
        $route = $this->getRoute($routeUrl);
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
    public function generate(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        // Symfony internal root, I assume.. Infinite loop due to "_profiler*" route, if not set
        if(str_starts_with($routeName, "_")) {
        
            try { return $this->router->generate($routeName, $routeParameters, $referenceType); }
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
        $routeName = explode(".", $routeName);
        $currentRouteName = explode(".", $this->getCurrentRouteName());

        $routeGroup = count($routeName) > 1 ? tail($routeName) : null;
        $routeGroup = $routeGroup ?? count($currentRouteName) > 1 ? tail($currentRouteName) : null;
        $routeGroup = $routeGroup ?? [];
        $routeGroup = $routeGroup ? ".".implode(".",$routeGroup) : null;

        $routeBase = $routeName[0];
        $routeName = $routeBase.$routeGroup;

        // Prepare the default route if not found.
        // In case a group doesn't exists, it will be replaced by the first group found in the route collection list.
        $routeGroups = $this->getRouteGroups($routeName);
        $routeDefaultGroup = first($routeGroups);
        $routeDefaultName = $routeBase.($routeDefaultGroup ? ".".$routeDefaultGroup : "");

        //
        // Strip unused variables from main group
        $routeUrl = $this->getUrl($routeName, $routeParameters);
        $routeGroupUrl = $this->getUrl($routeName.$routeGroup, $routeParameters);
        
        if($routeGroupUrl !== null && $routeUrl !== null) {

            $keys = array_keys(array_diff_key($this->getRouteParameters($routeUrl), $this->getRouteParameters($routeGroupUrl)));
            $routeParameters = array_key_removes($routeParameters, ...$keys);
        }
        
        // Try to compute subgroup (or base one)
        try { $routeUrl = $baseDir . $this->router->generate($routeName, $routeParameters, $referenceType); }
        catch (Exception $e) { $routeUrl = $baseDir . $this->router->generate($routeDefaultName, $routeParameters, $referenceType); }

        // Clean up double slashes..
        $parts = parse_url($routeUrl);
        $parts["path"] = str_rstrip(str_replace("//", "/", $parts["path"]), "/");
        $routeUrl = build_url($parts);

        return $routeUrl;
    }
}