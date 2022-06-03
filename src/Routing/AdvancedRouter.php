<?php

namespace Base\Routing;

use Base\Routing\Generator\AdvancedUrlGenerator;
use Base\Routing\Matcher\AdvancedUrlMatcher;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

class AdvancedRouter implements AdvancedRouterInterface
{
    protected $router;

    public function __construct(Router $router, RequestStack $requestStack, ParameterBagInterface $parameterBag, SettingBag $settingBag, string $debug)
    {
        $this->debug  = $debug;

        $this->router = $router;
        $this->router->setOption("matcher_class", AdvancedUrlMatcher::class);
        $this->router->setOption("generator_class", AdvancedUrlGenerator::class);

        $this->requestStack = $requestStack;
        $this->settingBag = $settingBag;
        $this->parameterBag = $parameterBag;

        $this->useCustomRouter = $parameterBag->get("base.router.use_custom_engine");
        $this->keepMachine     = $parameterBag->get("base.router.shorten.keep_machine");
        $this->keepSubdomain   = $parameterBag->get("base.router.shorten.keep_subdomain");
    }

    public function isProfiler($request = null)
    {
        if(!$request) $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof KernelEvent)
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
        if(!$request) $request = $this->requestStack->getCurrentRequest();
        if($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();
        else if(!$request instanceof Request)
            throw new \InvalidArgumentException("Invalid argument provided, expected either RequestStack or Request");

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

    public function getRouteCollection(): RouteCollection { return $this->router->getRouteCollection(); }
    public function getContext(): RequestContext { return $this->router->getContext(); }
    public function setContext(RequestContext $context) { $this->router->setContext($context); }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string { return $this->router->generate($name, $parameters, $referenceType); }

    public function match(string $pathinfo)       : array { return $this->router->match($pathinfo); }
    public function matchRequest(Request $request): array { return $this->router->matchRequest($request); }

    public function warmUp(string $cacheDir): array
    {
        if(getenv("SHELL_VERBOSITY") > 0 && php_sapi_name() == "cli") echo " // Warming up cache... Advanced router".PHP_EOL.PHP_EOL;
        return method_exists($this->router, "warmUp") ? $this->router->warmUp($cacheDir) : $this->getRouteCollection();
    }

    public function getRequestUri(): ?string { return $this->getRequest() ? $this->getRequest()->getRequestUri() : $_SERVER["REQUEST_URI"] ?? null; }
    public function getRequest(): ?Request { return $this->requestStack ? $this->requestStack->getCurrentRequest() : null; }
    public function getRoute(?string $routeUrl = null, ): ?Route
    {
        if ($routeUrl === null) $routeUrl = $this->getRequestUri();

        $routeMatch = $this->getRouteMatch($routeUrl);
        if(!$routeMatch) return null;

        $routeName = $routeMatch["_route"];
        if(array_key_exists("_group", $routeMatch))
            $routeName .= ".".$routeMatch["_group"];

        $routeName = $routeMatch["_route"];
        if(array_key_exists("_locale", $routeMatch))
            $routeName .= ".".$routeMatch["_locale"];

        return $this->router->getRouteCollection()->get($routeName);
    }

    public function hasRoute(string $routeName): bool { return $this->getRouteName($routeName) !== null; }
    public function getRouteName(?string $routeUrl = null): ?string
    {
        if(!$routeUrl) return $this->getRouteName($this->getRequestUri());
        $routeMatch = $this->getRouteMatch($routeUrl);
        return $routeMatch ? $routeMatch["_route"] : null;
    }

    public function getRouteParameters(?string $routeUrl = null): array
    {
        $route = $this->getRoute($routeUrl);
        return $route ? $route->getDefaults() : [];
    }

    public function getRouteMatch(?string $routeUrl = null): ?array
    {
        if(!$routeUrl) return null;

        try { return $this->match($routeUrl); }
        catch (ResourceNotFoundException $e) { return null; }
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
}
