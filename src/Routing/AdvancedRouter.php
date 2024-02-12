<?php

namespace Base\Routing;

use Base\Routing\Generator\AdvancedUrlGenerator;
use Base\Routing\Matcher\AdvancedUrlMatcher;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use InvalidArgumentException;
use Psr\Cache\CacheItemInterface;
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
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Contracts\Cache\CacheInterface;

use Symfony\Contracts\EventDispatcher\Event;

/**
 *
 */
class AdvancedRouter extends Router implements RouterInterface
{
    /**
     * @var Router
     */
    protected Router $router;

    /**
     * @var RequestStack
     */
    protected RequestStack $requestStack;

    /**
     * @var ParameterBagInterface
     */
    protected ParameterBagInterface $parameterBag;

    /**
     * @var LocalizerInterface
     */
    protected LocalizerInterface $localizer;

    /**
     * @var AssetExtension
     */
    protected AssetExtension $assetTwigExtension;

    /**
     * @var CacheInterface
     */
    protected CacheInterface $cache;
    protected CacheItemInterface $cacheRoutes;
    protected CacheItemInterface $cacheRouteMatches;
    protected CacheItemInterface $cacheRouteGroups;

    protected FirewallMapInterface $firewallMap;

    protected string $environment;

    protected bool $debug;
    protected ?string $cacheName;

    protected bool $useAdvancedFeatures;
    protected bool $useFallbacks;

    public function getLocaleLang(?string $lang = null): string
    {
        return $this->localizer->getLocaleLang($lang);
    }

    public function getLocale(?string $locale = null): string
    {
        return $this->localizer->getLocale($locale);
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function __construct(Router $router, RequestStack $requestStack, FirewallMapInterface $firewallMap, ParameterBagInterface $parameterBag, LocalizerInterface $localizer, AssetExtension $assetTwigExtension, CacheInterface $cache, string $debug, string $environment)
    {
        $this->debug = $debug;
        $this->environment = $environment;

        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->parameterBag = $parameterBag;
        $this->localizer = $localizer;
        $this->assetTwigExtension = $assetTwigExtension;
        $this->firewallMap = $firewallMap;

        $this->cache = $cache;
        $this->cacheName = "router." . hash('md5', self::class);
        $this->cacheRoutes = $this->cache->getItem($this->cacheName . ".routes");
        $this->cacheRouteMatches = $this->cache->getItem($this->cacheName . ".route_matches");
        $this->cacheRouteGroups = $this->cache->getItem($this->cacheName . ".route_groups");

        $this->useAdvancedFeatures = $parameterBag->get("base.router.use_custom") ?? false;
        $this->useFallbacks = $parameterBag->get("base.router.use_fallbacks") ?? false;

        AdvancedUrlMatcher::$router = $this;
        $this->router->setOption("matcher_class", AdvancedUrlMatcher::class);
        AdvancedUrlGenerator::$router = $this;
        $this->router->setOption("generator_class", AdvancedUrlGenerator::class);
    }

    public function useAdvancedFeatures(): bool
    {
        return $this->useAdvancedFeatures;
    }

    public function useFallbacks(): bool
    {
        return $this->useFallbacks;
    }

    public function getCache(): CacheInterface
    {
        return $this->cache;
    }

    public function getCacheName(): string
    {
        return $this->cacheName;
    }

    /**
     * @return mixed|CacheItemInterface
     */
    public function getCacheRoutes()
    {
        return $this->cacheRoutes;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if (getenv("SHELL_VERBOSITY") > 0 && php_sapi_name() == "cli") {
            echo " // Warming up cache... Advanced router" . PHP_EOL . PHP_EOL;
        }

        return $this->router->warmUp($cacheDir, $buildDir);
    }

    public function isCli(): bool
    {
        return is_cli();
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function isBackend(mixed $request = null): bool
    {
        return $this->isEasyAdmin($request) || $this->isProfiler($request);
    }

    public function isProfiler(mixed $request = null): bool
    {
        if (!$request) {
            $request = $this->requestStack->getCurrentRequest();
        }
        if ($request instanceof KernelEvent) {
            $request = $request->getRequest();
        } elseif ($request instanceof RequestStack) {
            $request = $request->getCurrentRequest();
        }

        $route = $this->getRouteName();
        if (!$route) {
            return false;
        }

        return str_starts_with($route, "_wdt") || str_starts_with($route, "_profiler");
    }

    public function isUX(mixed $request = null): bool
    {
        if (!$request) {
            $request = $this->requestStack->getCurrentRequest();
        }
        if ($request instanceof KernelEvent) {
            $request = $request->getRequest();
        } elseif ($request instanceof RequestStack) {
            $request = $request->getCurrentRequest();
        } elseif (!$request instanceof Request) {
            return false;
        }

        $route = $this->getRouteName();
        if (!$route) {
            return false;
        }

        return str_starts_with($route, "ux_");
    }

    public function isSecured(mixed $request = null): bool
    {
        if (!$request) {
            $request = $this->requestStack->getCurrentRequest();
        }
        if ($request instanceof KernelEvent) {
            $request = $request->getRequest();
        } elseif ($request instanceof RequestStack) {
            $request = $request->getCurrentRequest();
        } elseif (!$request instanceof Request) {
            return false;
        }

        $route = $this->getRouteName();
        if (!$route) {
            return false;
        }

        return str_starts_with($route, "security_");
    }

    public function isWdt(mixed $request = null): bool
    {
        if (!$request) {
            $request = $this->requestStack->getCurrentRequest();
        }
        if ($request instanceof KernelEvent) {
            $request = $request->getRequest();
        } elseif ($request instanceof RequestStack) {
            $request = $request->getCurrentRequest();
        } elseif (!$request instanceof Request) {
            return false;
        }

        $route = $this->getRouteName();
        if (!$route) {
            return false;
        }

        return str_starts_with($route, "_wdt");
    }

    public function isEasyAdmin(mixed $request = null): bool
    {
        if (!$request) {
            $request = $this->requestStack->getCurrentRequest();
        }
        if ($request instanceof KernelEvent) {
            $request = $request->getRequest();
        } elseif ($request instanceof RequestStack) {
            $request = $request->getCurrentRequest();
        } elseif (!$request instanceof Request) {
            return false;
        }

        $controllerAttribute = $request->attributes->get("_controller");
        $array = is_array($controllerAttribute) ? $controllerAttribute : explode("::", $request->attributes->get("_controller") ?? "");
        $controller = explode("::", $array[0] ?? "")[0];

        $parents = [];

        $parent = $controller;
        while (class_exists($parent) && ($parent = get_parent_class($parent))) {
            $parents[] = $parent;
        }

        $eaParents = array_filter($parents, fn($c) => str_starts_with($c, "EasyCorp\Bundle\EasyAdminBundle"));
        return !empty($eaParents);
    }

    public function getUrl(?string $nameOrUrl = null, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $nameOrUrl ??= get_url();
        $nameOrUrl = trim($nameOrUrl);
        if ($referenceType == self::ABSOLUTE_PATH) {
            $host = $this->getScheme() . "://" . $this->getHost();
            if (str_starts_with($nameOrUrl, $host)) {
                $nameOrUrl = preg_replace('#^.+://[^/]+#', '', $nameOrUrl);
            }
        }

        if (filter_var($nameOrUrl, FILTER_VALIDATE_URL) || str_contains($nameOrUrl, "/")) {
            if (!str_contains($nameOrUrl, "://") && $referenceType == self::ABSOLUTE_URL) {
                return $this->getScheme() . "://" . $this->getHost() . "/" . str_lstrip($this->getBaseDir(), "/") . str_lstrip($nameOrUrl, "/");
            }

            return $nameOrUrl;
        }

        return trim($this->generate($nameOrUrl, $parameters, $referenceType));
    }

    public function getAssetUrl(?string $nameOrUrl = null, ?string $packageName = null): string
    {
        return $this->assetTwigExtension->getAssetUrl($nameOrUrl ?? get_url(), $packageName);
    }

    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }

    public function setContext(RequestContext $context): void
    {
        $this->router->setContext($context);
    }

    public function getGenerator(): UrlGeneratorInterface
    {
        return $this->router->getGenerator();
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return $this->router->generate($name, $parameters, $referenceType);
    }

    public function match(string $pathinfo): array
    {
        return $this->router->match($pathinfo);
    }

    public function matchRequest(Request $request): array
    {
        return $this->router->matchRequest($request);
    }

    public function getMatcher(): UrlMatcherInterface
    {
        return $this->router->getMatcher();
    }

    public function getLocalizer(): LocalizerInterface
    {
        return $this->localizer;
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->router->getRouteCollection();
    }

    public function getRequestUri(): ?string
    {
        return $this->getRequest() ? $this->getRequest()->getRequestUri() : $_SERVER["REQUEST_URI"] ?? null;
    }

    public function getRequest(): ?Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    public function getMainRequest(): ?Request
    {
        return $this->requestStack->getMainRequest();
    }

    //
    // NB: Don't get confused, here. This route is not same as annotations and...
    // ... it is computed based on compiled routes from generator (not matcher)
    protected array $routes = [];

    public function getRoute(?string $routeNameOrUrl = null): ?Route
    {
        if ($routeNameOrUrl === null) {
            $routeNameOrUrl = $this->getRequestUri();
        }

        $routeName = $this->getRouteName($routeNameOrUrl);
        if (array_key_exists($routeName, $this->routes)) {
            return $this->routes[$routeName];
        }

        $generator = $this->getGenerator();
        $matcher = $this->getMatcher();
        $lang = $this->localizer->getLocaleLang();

        $compiledRoutes = $generator->getCompiledRoutes();
        $compiledRoute = $compiledRoutes[$routeName] ?? $compiledRoutes[$routeName . "." . $lang] ?? null;

        if ($compiledRoute !== null) {
            $args = array_transforms(fn($k, $v): array => [$k, in_array($k, [3, 4]) ? $matcher->getCompiledPath($v) : $v], $compiledRoute);
            $locale = $args[1]["_locale"] ?? null;
            $locale = $locale ? ["_locale" => $locale] : [];

            $search = array_map(fn($k) => "{" . $k . "}", array_keys($args[1]));
            $replace = array_values($args[1]);
            $args[4] = str_replace($search, $replace, $args[4]);

            $this->routes[$routeName] = new Route(
                $args[3],
                $args[1],
                array_merge($locale, $args[2]),
                [],
                $args[4],
                $args[5],
                $args[6]
            );

            return $this->routes[$routeName];
        }

        return null;
    }

    public function reducesOnFallback(?string $locale = null, ?string $environment = null): bool
    {
        $fallbacks = array_search_by($this->parameterBag->get("base.router.host_fallbacks"), "locale", $this->localizer->getLocale($locale));
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.host_fallbacks"), "locale", $this->localizer->getLocaleLang($locale));
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.host_fallbacks"), "locale", $this->localizer->getDefaultLocale());
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.host_fallbacks"), "locale", $this->localizer->getDefaultLocaleLang());
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.host_fallbacks"), "locale", null) ?? [];

        if ($environment) {
            $fallbacks = array_filter($fallbacks, fn($h) => ($h["env"] ?? null) == $environment);
        }

        $fallback = first($fallbacks);
        return $fallback["reduction"] ?? false;
    }

    protected function getFallbackParameters(?string $locale = null, ?string $environment = null): ?array
    {
        if (!$this->useFallbacks()) {
            return null;
        }

        $fallbacks = array_search_by($this->parameterBag->get("base.router.host_fallbacks"), "locale", $this->localizer->getLocale($locale));
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.host_fallbacks"), "locale", $this->localizer->getLocaleLang($locale));
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.host_fallbacks"), "locale", $this->localizer->getDefaultLocale());
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.host_fallbacks"), "locale", $this->localizer->getDefaultLocaleLang());
        $fallbacks ??= array_search_by($this->parameterBag->get("base.router.host_fallbacks"), "locale", null) ?? [];

        if ($environment) {
            $fallbacks = array_filter($fallbacks, fn($h) => ($h["env"] ?? null) == $environment);
        }

        $fallback = first($fallbacks);
        return array_map(fn($h) => is_array($h) || $h === null ? $h : [$h], $fallback);
    }

    public function getScheme(?string $locale = null, ?string $environment = null): string
    {
        $use_https ??= $_SERVER["REQUEST_SCHEME"] ?? true;
        return $use_https ? "https" : "http";
    }

    public function getBaseDir(?string $locale = null, ?string $environment = null): string
    {
        $host = $this->getFallbackParameters($locale, $environment);
        if (array_key_exists("SYMFONY_PROJECT_DEFAULT_ROUTE_PATH", $_SERVER)) {
            return $_SERVER['SYMFONY_PROJECT_DEFAULT_ROUTE_PATH'];
        }

        if (!is_cli()) {
            $baseDir = $_SERVER['PHP_SELF'] ? dirname($_SERVER['PHP_SELF']) : null;
        }

        $baseDir ??= first($host["base_dir"]) ?? "";
        return $baseDir;
    }

    public function getHostFallback(?string $locale = null, ?string $environment = null): string
    {
        $host = $this->getFallbackParameters($locale, $environment);

        $machine = $host["machine"] ?? null;
        $machine = is_array($machine) && first($machine) != false ? first($machine) . "." : "";

        $subdomain = $host["subdomain"] ?? null;
        $subdomain = is_array($subdomain) && first($subdomain) != false ? first($subdomain) . "." : "";

        $domain = $host["domain"] ?? null;
        $domain = is_array($domain) && first($domain) != false ? first($domain) : "";

        $port = $host["port"] ?? null;
        $port = is_array($port) && first($port) != false && !in_array(first($port), [80, 443]) ? ":" . first($port) : "";

        return $machine . $subdomain . $domain . $port;
    }

    public function getHost(?string $locale = null, ?string $environment = null): string
    {
        $host = compose_url(null, null, null, $this->getMachine(), $this->getSubdomain(), $this->getDomain(), $this->getPort());
        return $host ?: $this->getHostFallback();
    }

    public function getPortFallbacks(?string $locale = null, ?string $environment = null): array
    {
        return $this->getFallbackParameters($locale, $environment)["port"] ?? [];
    }

    public function getPortFallback(?string $locale = null, ?string $environment = null): ?int
    {
        $portFallback = first($this->getPortFallbacks($locale, $environment));
        $portFallback = in_array($portFallback ?? 80, [80, 443]) ? null : $portFallback;
        return $portFallback ? intval($portFallback) : null;
    }

    public function getPort(?string $locale = null, ?string $environment = null): ?int
    {
        $parsedUrl = parse_url2(get_url());
        $port = $parsedUrl["port"] ?? null;
        if (!in_array($port, $this->getPortFallbacks())) {
            $port = $this->getPortFallback();
        }

        return in_array($port ?? 80, [80, 443]) ? null : $port;
    }

    public function getMachineFallbacks(?string $locale = null, ?string $environment = null): array
    {
        return $this->getFallbackParameters($locale, $environment)["machine"] ?? [];
    }

    public function getMachineFallback(?string $locale = null, ?string $environment = null): ?string
    {
        $machineFallback = first($this->getMachineFallbacks($locale, $environment));
        return $machineFallback ?: null;
    }

    public function getMachine(?string $locale = null, ?string $environment = null): ?string
    {
        $parsedUrl = parse_url2(get_url());
        if (!in_array($parsedUrl["machine"] ?? null, $this->getMachineFallbacks())) {
            return $this->getMachineFallback();
        }

        return $parsedUrl["machine"] ?? null;
    }

    public function getSubdomainFallbacks(?string $locale = null, ?string $environment = null): array
    {
        return $this->getFallbackParameters($locale, $environment)["subdomain"] ?? [];
    }

    public function getSubdomainFallback(?string $locale = null, ?string $environment = null): ?string
    {
        $subdomainFallback = first($this->getSubdomainFallbacks($locale, $environment));
        return $subdomainFallback ?: null;
    }

    public function getSubdomain(?string $locale = null, ?string $environment = null): ?string
    {
        $parsedUrl = parse_url2(get_url());
        if (!in_array($parsedUrl["subdomain"] ?? null, $this->getSubdomainFallbacks())) {
            return $this->getSubdomainFallback();
        }

        return $parsedUrl["subdomain"] ?? null;
    }

    public function getDomainFallbacks(?string $locale = null, ?string $environment = null): array
    {
        return $this->getFallbackParameters($locale, $environment)["domain"] ?? [];
    }

    public function getDomainFallback(?string $locale = null, ?string $environment = null): ?string
    {
        $domainFallback = first($this->getDomainFallbacks($locale, $environment));
        return $domainFallback ?: null;
    }

    public function getDomain(?string $locale = null, ?string $environment = null): ?string
    {
        $parsedUrl = parse_url2(get_url());
        if (!in_array($parsedUrl["domain"] ?? null, $this->getDomainFallbacks())) {
            return $this->getDomainFallback();
        }

        return $parsedUrl["domain"] ?? null;
    }

    public function getRouteIndex(): string
    {
        return $this->parameterBag->get("base.site.index") ?? $this->getRouteName("/");
    }

    public function getUrlIndex(): string
    {
        return $this->getUrl($this->getRouteIndex());
    }

    public function getRouteName(?string $routeUrl = null): ?string
    {
        if ($this->getRequestUri() && !$routeUrl) {
            return $this->getRouteName($this->getRequestUri());
        }
        if ($routeUrl && !str_contains($routeUrl, "/")) {
            return $routeUrl;
        }

        $routeMatch = $this->getRouteMatch($routeUrl) ?? [];
        $isLocalized = ($routeMatch["_locale"] ?? false) && $routeMatch["_locale"] != $this->localizer->getDefaultLocaleLang();
        return $routeMatch ? $routeMatch["_route"] . ($isLocalized ? "." . $routeMatch["_locale"] : "") : null;
    }

    public function getRouteGroups(string $routeNameOrUrl): array
    {
        $routeName = $this->getRouteName($routeNameOrUrl);

        $generator = $this->getGenerator();
        if ($generator instanceof AdvancedUrlGenerator) {
            return $generator->groups($routeName);
        }

        return $routeName ? [$routeName] : [];
    }

    public function getRouteMatch(?string $routeUrl = null): ?array
    {
        if ($routeUrl === null) {
            $routeUrl = $this->getRequestUri();
        }

        try {
            return $routeUrl ? $this->match($routeUrl) : null;
        } catch (ResourceNotFoundException $e) {
            return null;
        }
    }

    public function getFirewallMap(): FirewallMapInterface
    {
        return $this->firewallMap;
    }

    public function hasFirewall(?string $routeUrl = null): ?bool
    {
        if ($routeUrl === null) {
            $routeUrl = $this->getRequestUri();
        }

        $matcher = $this->getMatcher();
        if ($matcher instanceof AdvancedUrlMatcher) {
            return $matcher->security($routeUrl);
        }

        return null;
    }

    public function getRouteFirewall(?string $routeUrl = null): ?FirewallConfig
    {
        if ($routeUrl === null) {
            $routeUrl = $this->getRequestUri();
        }

        $matcher = $this->getMatcher();
        if ($matcher instanceof AdvancedUrlMatcher) {
            return $matcher->firewall($routeUrl);
        }

        return null;
    }

    public function getRouteHash(string $routeNameOrUrl): string
    {
        $context = cast_to_array($this->getContext());
        array_pop_key("parameters", $context);
 
        return $routeNameOrUrl . ";" . serialize($context) . ";" . $this->localizer->getLocaleLang();
    }

    public function redirect(string $urlOrRoute, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse
    {
        if (filter_var($urlOrRoute, FILTER_VALIDATE_URL) || str_contains($urlOrRoute, "/")) {
            return new RedirectResponse($urlOrRoute);
        }

        return new RedirectResponse($this->generate($urlOrRoute, $routeParameters), $state, $headers);
    }

    public function redirectToRoute(string $routeName, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse
    {
        $routeNameBak = $routeName;

        $callback = null;
        if (array_key_exists("callback", $headers)) {
            $callback = $headers["callback"];
            if (!is_callable($callback)) {
                throw new InvalidArgumentException("header variable \"callback\" must be callable, value received: " . (is_object($callback) ? get_class($callback) : gettype($callback)));
            }

            unset($headers["callback"]);
        }

        $url = $this->generate($routeName, $routeParameters, 0) ?? $routeName;
        $routeName = $this->getRouteName($url);

        if (!$routeName) {
            throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeNameBak));
        }

        if ($routeName == $this->getRouteName()) {
            if ($this->getRouteIndex() == $this->getRouteName()) {
                throw new LogicException("Index page is not accessible.");
            }

            return $this->redirectToRoute($this->getRouteIndex());
        }

        $response = new RedirectResponse($url, $state, $headers);

        // Callable action if redirection happens
        if (is_callable($callback)) {
            $callback();
        }

        return $response;
    }

    public function redirectEvent(Event $event, string $routeName, array $routeParameters = [], int $state = 302, array $headers = []): bool
    {
        if (!method_exists($event, "setResponse")) {
            return false;
        }

        $routeNameBak = $routeName;

        $exceptions = [];
        if (array_key_exists("exceptions", $headers)) {
            $exceptions = $headers["exceptions"];
            if (!is_string($exceptions) && !is_array($exceptions)) {
                throw new InvalidArgumentException("header variable \"exceptions\" must be of type \"array\" or \"string\", value received: " . (is_object($exceptions) ? get_class($exceptions) : gettype($exceptions)));
            }

            unset($headers["exceptions"]);
        }

        $callback = null;
        if (array_key_exists("callback", $headers)) {
            $callback = $headers["callback"];
            if (!is_callable($callback)) {
                throw new InvalidArgumentException("header variable \"callback\" must be callable, value received: " . (is_object($callback) ? get_class($callback) : gettype($callback)));
            }

            unset($headers["callback"]);
        }

        $url = $this->generate($routeName, $routeParameters) ?? $routeName;
        $routeName = $this->getRouteName($url);
        if (!$routeName) {
            throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeNameBak));
        }

        $exceptions = is_string($exceptions) ? [$exceptions] : $exceptions;

        foreach ($exceptions as $pattern) {
            if (preg_match($pattern, $this->getRouteName())) {
                return false;
            }
        }

        $response = new RedirectResponse($url, $state, $headers);
        $event->setResponse($response);

        // Callable action if redirection happens
        if (is_callable($callback)) {
            $callback();
        }

        return true;
    }

    public function reloadRequest(?Request $request = null): RedirectResponse
    {
        $request = $request ?? $this->getRequest();
        return $this->redirect($request->get('_route'));
    }
}
