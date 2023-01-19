<?php

namespace Base\Routing;

use Base\Routing\Generator\AdvancedUrlGenerator;
use Base\Routing\Matcher\AdvancedUrlMatcher;
use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use InvalidArgumentException;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Contracts\Cache\CacheInterface;

use Symfony\Contracts\EventDispatcher\Event;

class AdvancedRouter implements RouterInterface
{
    /**
     * @var Router
     */
    protected $router;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var ParameterBag
     */
    protected $parameterBag;

    /**
     * @var LocaleProvider
     */
    protected $localeProvider;

    /**
     * @var AssetExtension
     */
    protected $assetTwigExtension;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var CacheInterface
     */
    protected $cacheRoutes;

    /**
     * @var CacheInterface
     */
    protected $cacheRouteMatches;

    /**
     * @var CacheInterface
     */
    protected $cacheRouteGroups;

    protected string  $environment;

    protected bool    $debug;
    protected ?string $cacheName;
    
    protected bool $useAdvancedFeatures;
    protected bool $keepMachine;
    protected bool $keepSubdomain;
    
    public function getLang   (?string $lang   = null) :string { return $this->localeProvider->getLang($lang);     }
    public function getLocale (?string $locale = null) :string { return $this->localeProvider->getLocale($locale); }
    public function getEnvironment()                   :string { return $this->environment; }

    public function __construct(Router $router, RequestStack $requestStack, ParameterBagInterface $parameterBag, LocaleProviderInterface $localeProvider, AssetExtension $assetTwigExtension, CacheInterface $cache, string $debug, string $environment)
    {
        $this->debug  = $debug;
        $this->environment = $environment;

        $this->router = $router;
        $this->router->setOption("matcher_class", AdvancedUrlMatcher::class);
        $this->router->setOption("generator_class", AdvancedUrlGenerator::class);

        $this->requestStack       = $requestStack;
        $this->parameterBag       = $parameterBag;
        $this->localeProvider     = $localeProvider;
        $this->assetTwigExtension = $assetTwigExtension;

        $this->cache             = $parameterBag->get("base.router.use_cache") ? $cache : null;
        $this->cacheName         = "router." . hash('md5', self::class);
        $this->cacheRoutes       = $this->cache ? $this->cache->getItem($this->cacheName.".routes") : null;
        $this->cacheRouteMatches = $this->cache ? $this->cache->getItem($this->cacheName.".route_matches" ) : null;
        $this->cacheRouteGroups  = $this->cache ? $this->cache->getItem($this->cacheName.".route_groups" ) : null;

        $this->useAdvancedFeatures = $parameterBag->get("base.router.use_advanced_features");
        $this->keepMachine     = $parameterBag->get("base.router.keep_machine");
        $this->keepSubdomain   = $parameterBag->get("base.router.keep_subdomain");
    }

    public function useAdvancedFeatures():bool { return $this->useAdvancedFeatures; }

    public function getCache() { return $this->cache; }
    public function getCacheRoutes() { return $this->cacheRoutes; }

    public function warmUp(string $cacheDir): array
    {
        if(getenv("SHELL_VERBOSITY") > 0 && php_sapi_name() == "cli")
            echo " // Warming up cache... Advanced router".PHP_EOL.PHP_EOL;

        return $this->router->warmUp($cacheDir);
    }

    public function isCli(): bool { return is_cli(); }
    public function isDebug(): bool { return $this->debug; }
    public function isBackend(mixed $request = null):bool { return $this->isEasyAdmin($request) || $this->isProfiler($request); }
    public function isProfiler(mixed $request = null):bool
    {
        if(!$request) $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();

        $route = $this->getRouteName();
        if(!$route) return false;

        return str_starts_with($route, "_wdt") || str_starts_with($route, "_profiler");
    }

    public function isUX(mixed $request = null):bool
    {
        if(!$request) $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();
        else if(!$request instanceof Request)
            return false;

        $route = $this->getRouteName();
        if(!$route) return false;

        return str_starts_with($route, "ux_");
    }

    public function isSecured(mixed $request = null):bool
    {
        if(!$request) $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();
        else if(!$request instanceof Request)
            return false;

        $route = $this->getRouteName();
        if(!$route) return false;

        return str_starts_with($route, "security_");
    }

    public function isWdt(mixed $request = null):bool
    {
        if(!$request) $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();
        else if(!$request instanceof Request)
            return false;

        $route = $this->getRouteName();
        if(!$route) return false;

        return str_starts_with($route, "_wdt");
    }

    public function isEasyAdmin(mixed $request = null):bool
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

    public function getUrl(?string $nameOrUrl = null, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $nameOrUrl ??= get_url();
        $nameOrUrl = trim($nameOrUrl);
        if($referenceType == self::ABSOLUTE_PATH) {

            $host = $this->getScheme()."://".$this->getHost();
            if(str_starts_with($nameOrUrl, $host))
                $nameOrUrl = preg_replace('#^.+://[^/]+#', '', $nameOrUrl);
        }

        if(filter_var($nameOrUrl, FILTER_VALIDATE_URL) || str_contains($nameOrUrl, "/")) {

            if(!str_contains($nameOrUrl, "://") && $referenceType == self::ABSOLUTE_URL)
                return $this->getScheme()."://".$this->getHost()."/".str_lstrip($this->getBaseDir(),"/").str_lstrip($nameOrUrl, "/");

            return $nameOrUrl;
        }

        return trim($this->generate($nameOrUrl, $parameters, $referenceType));
    }

    public function getAssetUrl(string $nameOrUrl, ?string $packageName = null): string { return $this->assetTwigExtension->getAssetUrl($nameOrUrl ?? get_url(), $packageName); }

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
    public function getMatcher(): UrlMatcherInterface { return $this->router->getMatcher(); }

    public function getRouteCollection(): RouteCollection { return $this->router->getRouteCollection(); }

    public function getRequestUri() : ?string  { return $this->getRequest() ? $this->getRequest()->getRequestUri() : $_SERVER["REQUEST_URI"] ?? null; }
    public function getRequest()    : ?Request { return $this->requestStack ? $this->requestStack->getCurrentRequest() : null; }
    public function getMainRequest(): ?Request { return $this->requestStack ? $this->requestStack->getMainRequest() : null; }

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
                $args[1],
                array_merge($locale, $args[2]), [],
                $args[4], $args[5], $args[6]
            );

            return $this->routes[$routeName];
        }

        return null;
    }

    protected function getHostParameters(?string $locale = null, ?string $environment = null): ?array
    {
        $fallbacks   = array_search_by($this->parameterBag->get("base.router.fallbacks"), "locale", $this->localeProvider->getLocale($locale));
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.fallbacks"), "locale", $this->localeProvider->getLang($locale));
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.fallbacks"), "locale", $this->localeProvider->getDefaultLocale($locale));
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.fallbacks"), "locale", $this->localeProvider->getDefaultLang($locale));
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.fallbacks"), "locale", null) ?? [];

        if($environment)
            $fallbacks = array_filter($fallbacks, fn($h) => $h["env"] ?? null == $environment);

        return first($fallbacks);
    }

    public function getScheme(?string $locale = null, ?string $environment = null): string
    {
        $host = $this->getHostParameters($locale, $environment);

        $use_https ??= $host["use_https"] ?? $_SERVER["REQUEST_SCHEME"] ?? true;
        return $use_https ? "https" : "http";
    }

    public function getBaseDir(?string $locale = null, ?string $environment = null): string
    {
        $host = $this->getHostParameters($locale, $environment);
        if(array_key_exists("SYMFONY_PROJECT_DEFAULT_ROUTE_PATH", $_SERVER))
            return $_SERVER['SYMFONY_PROJECT_DEFAULT_ROUTE_PATH'];

        if(!is_cli()) $baseDir = $_SERVER['PHP_SELF'] ? dirname($_SERVER['PHP_SELF']) : null;
        $baseDir ??= $host["base_dir"] ?? "";

        return $baseDir;
    }

    public function getHost(?string $locale = null, ?string $environment = null): string
    {
        return $_SERVER['HTTP_HOST'] ?? $this->getHostFallback();
    }

    public function getHostFallback(?string $locale = null, ?string $environment = null): string
    {
        $host = $this->getHostParameters($locale, $environment);

        $machine = $host["machine"] ?? null;
        if($machine) $machine = $machine . ".";

        $subdomain = $host["subdomain"] ?? null;
        if($subdomain) $subdomain = $subdomain . ".";

        $domain = $host["domain"] ?? null;

        $port   = $host["port"] ?? null;
        if ($port == 80 || $port == 443) $port = null;
        if ($port) $port = ":" . $port;

        return $machine.$subdomain.$domain.$port;
    }

    public function getMachine(?string $locale = null, ?string $environment = null): ?string { return $this->getHostParameters($locale, $environment)["machine"] ?? null; }
    public function getSubdomain(?string $locale = null, ?string $environment = null): ?string { return $this->getHostParameters($locale, $environment)["subdomain"] ?? null; }
    public function getDomain(?string $locale = null, ?string $environment = null): string { return $this->getHostParameters($locale, $environment)["domain"] ?? null; }
    public function getPort(?string $locale = null, ?string $environment = null): ?int
    {
        $host = $this->getHostParameters($locale, $environment);
        $port = $host["port"] ?? null;
        return in_array($port, [80, 443]) ? null : $port;
    }

    public function getIndexPage():string { return $this->parameterBag->get("base.site.index") ?? $this->getUrl("/"); }

    public function getRouteName(?string $routeUrl = null): ?string
    {
        if($this->getRequestUri() && !$routeUrl) return $this->getRouteName($this->getRequestUri());
        if($routeUrl && !str_contains($routeUrl, "/")) return $routeUrl;

        $routeMatch = $this->getRouteMatch($routeUrl) ?? [];

        $isLocalized = ($routeMatch["_locale"] ?? false) && $routeMatch["_locale"] != $this->localeProvider->getDefaultLang();
        return $routeMatch ? $routeMatch["_route"] . ($isLocalized ? "." . $routeMatch["_locale"] : "") : null;
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

        try { return $routeUrl ? $this->match($routeUrl) : null; }
        catch (ResourceNotFoundException $e) { return null; }
    }

    public function hasFirewall(?string $routeUrl = null): ?bool
    {
        if($routeUrl === null) $routeUrl = $this->getRequestUri();

        $matcher = $this->router->getMatcher();
        if ($matcher instanceof AdvancedUrlMatcher)
           return $matcher->security($routeUrl);

        return null;
    }

    public function getRouteFirewall(?string $routeUrl = null): ?FirewallConfig
    {
        if($routeUrl === null) $routeUrl = $this->getRequestUri();

        $matcher = $this->router->getMatcher();
        if ($matcher instanceof AdvancedUrlMatcher)
           return $matcher->firewall($routeUrl);

        return null;
    }

    public function getRouteHash(string $routeNameOrUrl): string
    {
        return $routeNameOrUrl . ";" . serialize($this->getContext()) . ";" . $this->localeProvider->getLang();
    }

    public function redirect(string $urlOrRoute, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse
    {
        if(filter_var($urlOrRoute, FILTER_VALIDATE_URL) || str_contains($urlOrRoute, "/"))
            return new RedirectResponse($urlOrRoute);

        return new RedirectResponse($this->generate($urlOrRoute, $routeParameters), $state, $headers);
    }

    public function redirectToRoute(string $routeName, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse
    {
        $routeNameBak = $routeName;

        $event = null;
        if(array_key_exists("event", $headers)) {

            $event = $headers["event"];
            if(! ($event instanceof Event) )
                throw new InvalidArgumentException("header variable \"event\" must be ".Event::class.", currently: ".(is_object($event) ? get_class($event) : gettype($event)));

            unset($headers["event"]);
        }

        $exceptions = [];
        if(array_key_exists("exceptions", $headers)) {

            $exceptions = $headers["exceptions"];
            if(!is_string($exceptions) && !is_array($exceptions))
                throw new InvalidArgumentException("header variable \"exceptions\" must be of type \"array\" or \"string\", currently: ".(is_object($exceptions) ? get_class($exceptions) : gettype($exceptions)));

            unset($headers["exceptions"]);
        }

        $callback = null;
        if(array_key_exists("callback", $headers)) {

            $callback = $headers["callback"];
            if(!is_callable($callback))
                throw new InvalidArgumentException("header variable \"callback\" must be callable, currently: ".(is_object($callback) ? get_class($callback) : gettype($callback)));

            unset($headers["callback"]);
        }

        $url   = $this->generate($routeName, $routeParameters) ?? $routeName;
        $routeName = $this->getRouteName($url);
        if (!$routeName) throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeNameBak));

        $exceptions = is_string($exceptions) ? [$exceptions] : $exceptions;
        foreach($exceptions as $pattern)
            if (preg_match($pattern, $this->getRouteName())) throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeNameBak));

        $response = new RedirectResponse($url, $state, $headers);
        if($event && method_exists($event, "setResponse")) $event->setResponse($response);

        // Callable action if redirection happens
        if(is_callable($callback)) $callback();

        return $response;
    }

    public function reloadRequest(?Request $request = null): RedirectResponse
    {
        $request = $request ?? $this->getRequest();
        return $this->redirect($request->get('_route'));
    }
}
