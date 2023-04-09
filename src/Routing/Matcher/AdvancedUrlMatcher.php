<?php

namespace Base\Routing\Matcher;

use Base\Routing\AdvancedRouter;
use Base\Routing\Generator\AdvancedUrlGenerator;
use Base\Routing\RouterInterface;
use Base\Service\Localizer;
use Base\Traits\BaseTrait;
use Exception;
use Generator;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherTrait;
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class AdvancedUrlMatcher extends CompiledUrlMatcher implements RedirectableUrlMatcherInterface
{
    use CompiledUrlMatcherTrait { CompiledUrlMatcherTrait::match as _match; }

    public static $router = null;
    public function __construct(array $compiledRoutes, RequestContext $context)
    {
        $this->context = $context;
        [$this->matchHost, $this->staticRoutes, $this->regexpList, $this->dynamicRoutes, $this->checkCondition] = $compiledRoutes;

        //
        // NB: Static routes using multiple host, or domains might be screened.. imo
        $cacheKey = self::$router->getCacheName().".static_routes[".self::$router->getLocale()."][".self::$router->getHost()."]";
        $this->staticRoutes = self::$router->getCache()->get($cacheKey, function() {

            $staticRoutes = [];
            foreach ($this->staticRoutes as &$staticRoute) {

                $host = $staticRoute[0][1];
                if(!$host) continue;

                $ipFallback = array_key_exists("ip", parse_url2(self::$router->getHostFallback()));
                if ($ipFallback && str_contains($host, ["\\\\\\{_machine\\\\\\}", "\\\\\\{_subdomain\\\\\\}"]))
                    throw new Exception("$IP address provided. This is incompatible with some routes using `machine` and `subdomain` features.");
                if (!self::$router->getDomainFallback() && str_contains($host, "\\\\\\{_domain\\\\\\}"))
                    throw new Exception("Domain fallback not provided. This is incompatible with some routes using `domain` features.");

                $machine = preg_quote(self::$router->getMachineFallback());
                if(in_array(self::$router->getMachine(), self::$router->getMachineFallbacks()))
                    $machine = preg_quote(self::$router->getMachine());

                $subdomain = preg_quote(self::$router->getSubdomainFallback());
                if(in_array(self::$router->getSubdomain(), self::$router->getSubdomainFallbacks()))
                    $subdomain = preg_quote(self::$router->getSubdomain());

                $domain = preg_quote(self::$router->getDomainFallback());
                if(in_array(self::$router->getDomain(), self::$router->getDomainFallbacks()))
                    $domain = preg_quote(self::$router->getDomain());

                $port = preg_quote(self::$router->getPortFallback());
                if(in_array(self::$router->getPort(), self::$router->getPortFallbacks()))
                    $port = preg_quote(self::$router->getPort());

                $search = ["\\\\\\{_machine\\\\\\}\.", "\\\\\\{_subdomain\\\\\\}\.", "\\\\\\{_domain\\\\\\}", ":\\\\\\{_port\\\\\\}"];
                $replace = [$machine ? $machine."\." : "", $subdomain ? $subdomain."\." : "", preg_quote($domain), $port == 80 || $port == 443 || !$port ? "" : ":".$port];

                $staticRoute[0][1] = str_replace($search, $replace, $host);
            }

            return $this->staticRoutes;
        });

        $cacheKey = self::$router->getCacheName().".dynamic_routes[".self::$router->getLocale()."][".self::$router->getHost()."]";
        [$this->regexpList, $this->dynamicRoutes] = self::$router->getCache()->get($cacheKey, function() {

            foreach ($this->regexpList as $offset => &$regexp) {

                $ipFallback = array_key_exists("ip", parse_url2(self::$router->getHostFallback()));
                if ($ipFallback && str_contains($regexp, ["\\\\\\{_machine\\\\\\}", "\\\\\\{_subdomain\\\\\\}"]))
                    throw new Exception("$IP address provided. This is incompatible with some routes using `machine` and `subdomain` features.");
                if (!self::$router->getDomainFallback() && str_contains($regexp, "\\\\\\{_domain\\\\\\}"))
                    throw new Exception("Domain fallback not provided. This is incompatible with some routes using `domain` features.");

                $machine = preg_quote(self::$router->getMachineFallback());
                if(in_array(self::$router->getMachine(), self::$router->getMachineFallbacks()))
                    $machine = preg_quote(self::$router->getMachine());

                $subdomain = preg_quote(self::$router->getSubdomainFallback());
                if(in_array(self::$router->getSubdomain(), self::$router->getSubdomainFallbacks()))
                    $subdomain = preg_quote(self::$router->getSubdomain());

                $domain = preg_quote(self::$router->getDomainFallback());
                if(in_array(self::$router->getDomain(), self::$router->getDomainFallbacks()))
                    $domain = preg_quote(self::$router->getDomain());

                $port = preg_quote(self::$router->getPortFallback());
                if(in_array(self::$router->getPort(), self::$router->getPortFallbacks()))
                    $port = preg_quote(self::$router->getPort());

                $search = ["\\\\\\{_machine\\\\\\}\.", "\\\\\\{_subdomain\\\\\\}\.", "\\\\\\{_domain\\\\\\}", ":\\\\\\{_port\\\\\\}"];
                $replace = [$machine ? $machine."\." : "", $subdomain ? $subdomain."\." : "", $domain, $port == 80 || $port == 443 || !$port ? "" : ":".$port];
                $regexp = str_replace($search, $replace, $regexp);
            }

            return $this->recomputeDynamicRoutes();
        });

    }

    public function redirect(string $path, string $route, string $scheme = null): array
    {
        return [
            '_controller' => RedirectController::class . '::urlRedirectAction',
            '_route'      => $route,

            'path'        => $path,
            'permanent'   => true,
            'scheme'      => $scheme,
            'httpPort'    => $this->context->getHttpPort(),
            'httpsPort'   => $this->context->getHttpsPort(),
        ];
    }

    public function security(string $pathinfo): bool
    {
        $request = Request::create($pathinfo, "GET", [], $_COOKIE, $_FILES, $_SERVER);
        return self::$router->getFirewallMap()?->getFirewallConfig($request)?->isSecurityEnabled() ?? false;
    }

    public function firewall(string $pathinfo): ?FirewallConfig
    {
        $request = Request::create($pathinfo, "GET", [], $_COOKIE, $_FILES, $_SERVER);
        return self::$router->getFirewallMap()?->getFirewallConfig($request);
    }

    private function recomputeDynamicRoutes(): array {

        if(empty($this->regexpList)) return [$this->regexpList, $this->dynamicRoutes];

        $splitRegexp = preg_split('/\(\*:([0-9]+)\)/', $this->regexpList[0]);
        $lastKey = array_key_last($splitRegexp);

        $dynamicRoute = $this->dynamicRoutes;
        $dynamicRouteKeys = array_keys($this->dynamicRoutes);

        $this->regexpList[0] = "";
        $this->dynamicRoutes = [];

        foreach($splitRegexp as $key => $pattern) {

            $this->regexpList[0] .= $pattern;
            if($key == $lastKey) continue;

            $this->regexpList[0] .= "(*";
            $n = strlen($this->regexpList[0]);
            $this->regexpList[0] .= ":".$n.")";

            $this->dynamicRoutes[$n] = $dynamicRoute[$dynamicRouteKeys[$key]];
        }

        return [$this->regexpList, $this->dynamicRoutes];
    }

    public function getCompiledPath(array $compiledRoute): ?string
    {
        $path = "";
        $parameters = array_reverse($compiledRoute ?? []);
        foreach ($parameters as $parameter) {
            $path .= $parameter[1];
            $path .= $parameter[3] ?? false ? "{".$parameter[3]."}" : "";
        }

        return $path;
    }

    public function getCompiledHost(array $compiledRoute): ?string
    {
        $host = null;
        foreach($compiledRoute[4] ?? [] as $part)
            $host = $part[1] . ($part[0] == "variable" ? "{".$part[3]."}" : "") . $host;

        return $host;
    }

    public function getCompiledHostRegex(array $compiledRoute): ?string {

        $regex = null;
        foreach($compiledRoute[4] ?? [] as $part)
            $regex = preg_quote($part[1]) . ($part[0] == "variable" ? $part[2] : "") . $regex;

        return $regex;
    }

    public function match(string $pathinfo): array
    {
        $parse = parse_url2($pathinfo) ?? [];
        if($parse) {
            if (array_key_exists("host", $parse))
                $this->getContext()->setHost($parse["host"] ?? "");
            if (array_key_exists("port", $parse))
                $this->getContext()->setHttpPort((int)($parse["port"] ?? 80));
            if (array_key_exists("port", $parse))
                $this->getContext()->setHttpsPort((int)($parse["port"] ?? 8000));
            if (array_key_exists("queryString", $parse))
                $this->getContext()->setQueryString($parse["queryString"] ?? "");
            if (array_key_exists("path", $parse))
                $this->getContext()->setBaseUrl("");

            $this->getContext()->setPathInfo("");
            $pathinfo = $parse["path"];
        }

        //
        // Prevent to match custom route with Symfony internal route.
        // NB: It breaks and gets infinite loop due to "_profiler*" route, if not set..
        try { $match = $this->_match($pathinfo); }
        catch (Exception $e) { $match = []; }

        if (str_starts_with($match["_route"] ?? "", "_") || !self::$router?->useAdvancedFeatures()) {
            return $match;
        }

        if (empty($match) || ($match["_controller"] ?? null) == RedirectController::class."::urlRedirectAction") {
            $match = $this->_match($pathinfo."/");
        }

        return $match;
    }
}
