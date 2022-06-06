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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
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

    public function warmUp(string $cacheDir): array
    {
        if(getenv("SHELL_VERBOSITY") > 0 && php_sapi_name() == "cli") echo " // Warming up cache... Advanced router".PHP_EOL.PHP_EOL;
        return method_exists($this->router, "warmUp") ? $this->router->warmUp($cacheDir) : [];
    }

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
    public function sanitize(string $url): string
    {
        if($url === null) $url = get_url();

        $generator = $this->router->getGenerator();
        if($generator instanceof AdvancedUrlGenerator)
            return $generator->sanitize($url);

        return $url;
    }

    public function getMatcher(): UrlMatcherInterface { return  $this->router->getMatcher(); }
    public function match(string $pathinfo)       : array { return $this->router->match($pathinfo); }
    public function matchRequest(Request $request): array { return $this->router->matchRequest($request); }

    public function getRouteCollection(): RouteCollection { return $this->router->getRouteCollection(); }

    public function getRequestUri(): ?string { return $this->getRequest() ? $this->getRequest()->getRequestUri() : $_SERVER["REQUEST_URI"] ?? null; }
    public function getRequest(): ?Request { return $this->requestStack ? $this->requestStack->getCurrentRequest() : null; }

    protected $routeList = [];
    public function getRoute(?string $routeNameOrUrl = null): ?Route
    {
        if ($routeNameOrUrl === null) $routeNameOrUrl = $this->getRequestUri();

        $routeName = $routeNameOrUrl;
        if (filter_var($routeNameOrUrl, FILTER_VALIDATE_URL))
            $routeName = $this->getRouteName($routeNameOrUrl);

        if(array_key_exists($routeName, $this->routeList))
            return $this->routeList[$routeName];

        $compiledRoutes = [];
        $generator = $this->router->getGenerator();
        $matcher   = $this->router->getMatcher();

        if(!$generator instanceof AdvancedUrlGenerator || !$matcher instanceof AdvancedUrlMatcher)
            return $this->router->getRouteCollection()->get($routeName) ?? null;

        $compiledRoutes = $generator->getCompiledRoutes();

        if(array_key_exists($routeName, $compiledRoutes)) {

            $args = array_transforms(fn($k, $v): array =>
                [$k, in_array($k, [3,4]) ? $matcher->path($v) : $v],
                $compiledRoutes[$routeName]
            );

            $this->routeList[$routeName] = new Route($args[3], array_intersect_key($args[1], array_flip($args[0])), $args[2], [], $args[4], $args[5], $args[6]);
            return $this->routeList[$routeName];
        }

        return null;
    }

    public function getRouteDefaults(?string $routeNameOrUrl = null): array
    {
        $route = $this->getRoute($routeNameOrUrl);
        return $route ? $route->getDefaults() : [];
    }

    public function getRouteName(?string $routeUrl = null): ?string
    {
        if($this->getRequestUri() && !$routeUrl) return $this->getRouteName($this->getRequestUri());

        $routeMatch = $this->getRouteMatch($routeUrl);
        return $routeMatch ? $routeMatch["_route"] : null;
    }

    public function getRouteGroups(?string $routeName): array
    {
        $generator = $this->router->getGenerator();
        if ($generator instanceof AdvancedUrlGenerator)
            $routeNames = array_keys($generator->getCompiledRoutes());

        // The next line should never be triggered as the generator is overloaded in __constructor
        if($routeNames === null) $routeNames = array_keys($this->getRouteCollection()->all());

        return $this->getMatcher()->groups($routeName);
    }

    public function getRouteMatch(?string $routeUrl = null): ?array
    {
        if(!$routeUrl) return null;

        try { return $this->match($routeUrl); }
        catch (ResourceNotFoundException $e) { return null; }
    }
}
