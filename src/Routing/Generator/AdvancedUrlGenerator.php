<?php

namespace Base\Routing\Generator;

use Base\BaseBundle;
use Base\Security\LoginFormAuthenticator;
use Base\Security\RescueFormAuthenticator;
use Base\Traits\BaseTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\RequestContext;

class AdvancedUrlGenerator extends CompiledUrlGenerator
{
    use BaseTrait;

    protected $cachedRoutes;
    protected $compiledRoutes;

    public function getCompiledRoutes():array { return $this->compiledRoutes; }

    public function __construct(array $compiledRoutes, RequestContext $context, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        // NB: This generator needs separate context.. Matcher class is changing context.
        $context = new RequestContext($context->getBaseUrl(), $context->getMethod(), $context->getHost(), $context->getScheme(), $context->getHttpPort(), $context->getHttpsPort(), $context->getPathInfo(), $context->getQueryString());
        parent::__construct($compiledRoutes, $context, $logger, $defaultLocale);
        
        $this->compiledRoutes = $compiledRoutes;
        $this->cachedRoutes   = BaseBundle::USE_CACHE && $this->getRouter()->getCache()
            ? ($this->getRouter()->getCacheRoutes()->get() ?? []) : [];
    }

    protected function resolveUrl(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH): ?string
    {
        // Transforms requested route by adding parameters
        if($routeName === null) return null;
        if(($route = $this->getRouter()->getRoute($routeName))) {

            if($route->getHost()) $referenceType = self::ABSOLUTE_URL;

            if(str_contains($route->getHost().$route->getPath(), "{") && str_contains($route->getHost().$route->getPath(), "}")) {

                if(preg_match_all("/{(\w*)}/", $route->getHost().$route->getPath(), $matches)) {

                    $parse = parse_url2(get_url());

                    $parameterNames = array_flip($matches[1]);
                    $routeParameters = array_merge(
                        array_intersect_key($parse, $parameterNames),
                        $route->getDefaults(),
                        $routeParameters,
                    );

                    $search  = array_map(fn($k) => "{".$k."}", array_keys($parse));
                    $replace = array_values($parse);

                    foreach($routeParameters as $key => &$routeParameter) {

                        $routeParameter = $routeParameter ? str_replace($search, $replace, $routeParameter) : $routeParameter;
                        if($key == "host") $routeParameter = str_lstrip($routeParameter, "www.");
                    }
                }
            }
        }

        //
        // Lookup for lang in current group
        $e = null;
        $routeParameters = array_filter($routeParameters, fn($p) => $p !== null);
        if(!str_ends_with($routeName, ".".$this->getRouter()->getLocaleLang())) {

            try { return sanitize_url(parent::generate($routeName.".".$this->getRouter()->getLocaleLang(), $routeParameters, $referenceType)); }
            catch (InvalidParameterException|RouteNotFoundException $_) { $e = $_; }
        }

        try { return sanitize_url(parent::generate($routeName, array_filter($routeParameters), $referenceType)); }
        catch (InvalidParameterException|RouteNotFoundException $_) { $e = $_; }

        //
        // Lookup for lang in default group
        $routeGroups  = $this->getRouter()->getRouteGroups($routeName);
        $routeDefaultName = array_filter($routeGroups, fn($r) => str_ends_with($r,".".$this->getRouter()->getLocaleLang()))[0] ?? null;
        if(!$routeDefaultName) throw $e;

        if(!str_ends_with($routeDefaultName, ".".$this->getRouter()->getLocaleLang())) {
            try { return sanitize_url(parent::generate($routeDefaultName.".".$this->getRouter()->getLocaleLang(), $routeParameters, $referenceType)); }
            catch (InvalidParameterException|RouteNotFoundException $_) { }
        }

        try { return sanitize_url(parent::generate($routeDefaultName, array_filter($routeParameters), $referenceType)); }
        catch (InvalidParameterException|RouteNotFoundException $_) { throw $e; }
    }

    public function resolveParameters(?array $routeParameters = null): ?array
    {
        if ($routeParameters === null){

            $parse = parse_url2(get_url(), -1, $this->getRouter()->getBaseDir()); // Make sure also it gets the basic context

        } else {

            // Use either parameters or $_SERVER variables to determine the host to provide
            $scheme    = array_pop_key("_scheme"  , $routeParameters) ?? $this->getRouter()->getScheme();
            $baseDir   = array_pop_key("_base_dir", $routeParameters) ?? $this->getRouter()->getBaseDir();
            $host      = array_pop_key("_host"    , $routeParameters) ?? $this->getRouter()->getHost();
            $port      = array_pop_key("_port"    , $routeParameters) ?? explode(":", $host)[1] ?? $this->getRouter()->getPort();
            $host      = explode(":", $host)[0].":".$port;

            $parse     = parse_url2(get_url($scheme, $host, $baseDir), -1, $baseDir);
            $parse["base_dir"] = $baseDir;
        }

        if($parse && array_key_exists("host", $parse))
            $this->getContext()->setHost($parse["host"]);
        if($parse && array_key_exists("base_dir", $parse))
            $this->getContext()->setBaseUrl($parse["base_dir"]);

        $this->getContext()->setHttpPort($parse["port"] ?? 80);
        $this->getContext()->setHttpsPort($parse["port"] ?? 443);
        $this->getContext()->setScheme($parse["scheme"] ?? "https");

        return $routeParameters;
    }

    public function generate(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        //
        // Prevent to generate custom route with Symfony internal route.
        // NB: It breaks and gets infinite loop due to "_profiler*" route, if not set..
        if(str_starts_with($routeName, "_") || !$this->getRouter()->useAdvancedFeatures()) {

            $routeParameters = $this->resolveParameters($routeParameters);
            try { return parent::generate($routeName, $routeParameters, $referenceType); }
            catch (Exception $e ) { throw $e; }
        }


        //
        // Extract locale from route name if found
        foreach($this->getLocalizer()->getAvailableLocaleLangs() as $lang) {

            if(str_ends_with($routeName, ".".$lang)) {

                $routeName = str_rstrip($routeName, ".".$lang);
                $locale = $lang;
            }
        }

        // Priority to route parameter locale
        if(array_key_exists("_locale", $routeParameters))
            $locale = $this->getLocalizer()->getLocaleLang($routeParameters["_locale"]);

        $routeParameters = $this->resolveParameters($routeParameters);

        // Check whether the route is already cached
        $hash = $this->getRouter()->getRouteHash($routeName, $routeParameters, $referenceType);
        if(array_key_exists($hash, $this->cachedRoutes)) {

            $cachedRoute = $this->cachedRoutes[$hash];
            return $this->resolveUrl($cachedRoute["_name"], $routeParameters, $referenceType);
        }

        $routeGroups  = $this->getRouter()->getRouteGroups($routeName);
        $routeDefaultName = first($routeGroups);
        if(array_key_exists("_locale", $routeParameters)) {

            $locale = $this->getLocalizer()->getLocaleLang($routeParameters["_locale"]);
            if(!str_ends_with($routeName, ".".$locale))
                $routeDefaultName .= ".".$this->getLocalizer()->getLocaleLang($routeParameters["_locale"]);
        }

        $routes = array_filter(array_transforms(fn($k, $routeName): array => [$routeName, $this->getRouter()->getRoute($routeName)], $routeGroups));

        //
        // Try to compute subgroup (if not found compute base)
        try { $routeUrl = $this->resolveUrl($routeName, $routeParameters, $referenceType); }
        catch(Exception $e) {

            if (str_starts_with($routeName, "app_") ) {

                $routeName = "base_".substr($routeName, 4);
                try { $routeUrl = $this->resolveUrl($routeName, $routeParameters, $referenceType); }
                catch(Exception $_) { throw $e; }

            } else if ($routeName == $routeDefaultName || $routeDefaultName === null) throw $e;

            $routeName = $routeDefaultName;
            $routeUrl = $this->resolveUrl($routeName, $routeParameters, $referenceType);
        }

        $cache = $this->getRouter()->getCache();
        if($cache !== null) {

            $routeRequirements = [];
            if(array_key_exists($routeName, $routes))
                $routeRequirements = $routes[$routeName]->getRequirements();

            $this->cachedRoutes[$hash] = [
                "_name" => $routeName,
                "_requirements" => $routeRequirements
            ];

            $cache->save($this->getRouter()->getCacheRoutes()->set($this->cachedRoutes));
        }

        return $routeUrl;
    }

    public function format(string $url): string
    {
        $permittedHosts   = array_search_by($this->getParameterBag()->get("base.router.permitted_hosts"), "locale", $this->getLocalizer()->getLocale());
        $permittedHosts ??= array_search_by($this->getParameterBag()->get("base.router.permitted_hosts"), "locale", $this->getLocalizer()->getLocaleLang());
        $permittedHosts ??= array_search_by($this->getParameterBag()->get("base.router.permitted_hosts"), "locale", $this->getLocalizer()->getDefaultLocale());
        $permittedHosts ??= array_search_by($this->getParameterBag()->get("base.router.permitted_hosts"), "locale", $this->getLocalizer()->getDefaultLocaleLang());
        $permittedHosts ??= array_search_by($this->getParameterBag()->get("base.router.permitted_hosts"), "locale", null) ?? [];
        $permittedHosts = array_transforms(fn($k, $a): ?array => $a["env"] == $this->getRouter()->getEnvironment() ? [$k, $a["regex"]] : null, $permittedHosts);
        if(!$this->getRouter()->keepMachine() && !$this->getRouter()->keepSubdomain())
            $permittedHosts = "^$"; // Special case if both subdomain and machine are unallowed

        $parse = parse_url2($url);

        $allowedHost = empty($permittedHosts);
        foreach($permittedHosts as $permittedHost)
            $allowedHost |= preg_match("/".$permittedHost."/", $parse["host"] ?? null);

        // Special case for login form.. to be redirected to rescue authenticator if no access right
        $routeName = $this->getRouter()->getRouteName();
        if(!LoginFormAuthenticator::isSecurityRoute($routeName) && !RescueFormAuthenticator::isSecurityRoute($routeName))
        {
            // Special case for WWW subdomain
            if(!array_key_exists("subdomain", $parse) && !array_key_exists("machine", $parse) && !$allowedHost) {
            
                $parse["subdomain"] = "www";
            
            } else if( array_key_exists("subdomain", $parse) && !$allowedHost) {

                if($parse["subdomain"] === "www") $parse = array_key_removes($parse, "subdomain");
                else $parse["subdomain"] = "www";
            }

            if(array_key_exists("machine",   $parse) && !$this->getRouter()->keepMachine()  )
                $parse = array_key_removes($parse, "machine");

            if(array_key_exists("subdomain", $parse) && !$this->getRouter()->keepSubdomain())
                if(array_key_exists("machine",   $parse) || !$this->getRouter()->keepMachine())
                    $parse = array_key_removes($parse, "subdomain");
        }

        return compose_url(
            $parse["scheme"] ?? null, $parse["user"] ?? null, $parse["password"] ?? null,
            $parse["machine"] ?? null, $parse["subdomain"] ?? null, $parse["domain"] ?? null, $parse["port"] ?? null,
            $parse["path"] ?? null,
        );
    }
}
