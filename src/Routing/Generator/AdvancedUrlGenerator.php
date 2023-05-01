<?php

namespace Base\Routing\Generator;

use Base\BaseBundle;
use Base\Service\Localizer;
use Exception;
use Generator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\RequestContext;

/**
 *
 */
class AdvancedUrlGenerator extends CompiledUrlGenerator
{
    public static $router = null;

    protected array $cachedRoutes = [];
    protected array $compiledRoutes;

    public function getCompiledRoutes(): array
    {
        return $this->compiledRoutes;
    }

    public function __construct(array $compiledRoutes, RequestContext $context, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        //
        // NB: Static routes using multiple host, or domains might be screened.. imo

        $reservedChars = ["{", "}", "(", ")", "/", "\\", "@", ":"];
        $replacementChars = array_pad([], count($reservedChars), "_");
        $cacheKey = str_replace($reservedChars, $replacementChars, self::$router->getCacheName() . ".compiled_routes[" . self::$router->getLocale() . "][" . self::$router->getHost() . "]");
        $compiledRoutes = self::$router->getCache()->get($cacheKey, function () use ($compiledRoutes) {
            foreach ($compiledRoutes as &$compiledRoute) {
                $machine = self::$router->getMachine() ?? null;
                $subdomain = self::$router->getSubdomain() ?? null;
                $domain = self::$router->getDomain() ?? null;
                $port = self::$router->getPort() ?? null;

                $search = ["\\{_machine\\}.", "\\{_subdomain\\}.", "\\{_domain\\}", ":\\{_port\\}"];
                $replace = [$machine ? $machine . "." : "", $subdomain ? $subdomain . "." : "", $domain, $port == 80 || $port == 443 || !$port ? "" : ":" . $port];

                foreach ($compiledRoute[4] as &$variable) {
                    $variable[1] = str_replace($search, $replace, $variable[1]);
                }
            }

            return $compiledRoutes;
        });

        parent::__construct($compiledRoutes, $context, $logger, $defaultLocale);
        $this->compiledRoutes = $compiledRoutes;
    }

    protected function resolveUrl(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH): ?string
    {
        // Transforms requested route by adding parameters

        if (($route = self::$router->getRoute($routeName))) {
            if ($route->getHost()) {
                $referenceType = self::ABSOLUTE_URL;
            }

            $host = $route->getHost() ? $route->getHost() : self::$router->getHost();
            $host = explode(":", $host)[0]; // Remove port from host..
            $this->getContext()->setHost($host);

            $port = self::$router->getPort() ?? 80;
            $this->getContext()->setHttpPort($port);
            $this->getContext()->setHttpsPort($port);

            $path = $route->getPath();

            if (str_contains($host . $path, "{") && str_contains($host . $path, "}")) {
                if (preg_match_all("/{(\w*)}/", $host . $path, $matches)) {
                    $parse = array_transforms(fn($k, $v): array => ["_" . $k, $v], parse_url2(get_url()));
                    $parameterNames = array_flip($matches[1]);

                    $routeParameters = array_merge(
                        array_intersect_key($parse, $parameterNames),
                        $route->getDefaults(),
                        $routeParameters,
                    );

                    $search = array_map(fn($k) => "{" . $k . "}", array_keys($parse));
                    $replace = array_values($parse);
                    foreach ($routeParameters as $key => $routeParameter) {
                        if (!$routeParameter) {
                            continue;
                        }
                        $routeParameters[$key] = str_replace($search, $replace, $routeParameter);
                    }
                }

                $routeParameters = $this->resolveParameters($routeParameters);
            }
        }

        //
        // Lookup for lang in current group
        $e = null;
        $routeParameters = array_filter($routeParameters, fn($p) => $p !== null);

        $routeUrl = null;
        if (!str_ends_with($routeName, "." . self::$router->getLocaleLang())) {
            try {
                $routeUrl = sanitize_url(parent::generate($routeName . "." . self::$router->getLocaleLang(), $routeParameters, $referenceType));
            } catch (InvalidParameterException|RouteNotFoundException $_) {
                $e = $_;
            }
        }

        if (!$routeUrl) {
            try {
                $routeUrl = sanitize_url(parent::generate($routeName, array_filter($routeParameters), $referenceType));
            } catch (InvalidParameterException|RouteNotFoundException $_) {
                $e = $_;
            }
        }

        if (!$routeUrl) {
            //
            // Lookup for lang in default group
            $routeGroups = self::$router->getRouteGroups($routeName);
            $routeDefaultName = array_filter($routeGroups, fn($r) => str_ends_with($r, "." . self::$router->getLocaleLang()))[0] ?? null;
            if (!$routeDefaultName) {
                throw $e;
            }

            if (!str_ends_with($routeDefaultName, "." . self::$router->getLocaleLang())) {
                try {
                    $routeUrl = sanitize_url(parent::generate($routeDefaultName . "." . self::$router->getLocaleLang(), $routeParameters, $referenceType));
                } catch (InvalidParameterException|RouteNotFoundException $_) {
                }
            }
        }

        if (!$routeUrl) {
            try {
                $routeUrl = sanitize_url(parent::generate($routeDefaultName, array_filter($routeParameters), $referenceType));
            } catch (InvalidParameterException|RouteNotFoundException $_) {
                throw $e;
            }
        }

        return $routeUrl;
    }

    public function resolveParameters(?array $routeParameters = null): ?array
    {
        if ($routeParameters === null) {
            $parse = parse_url2(get_url(), -1, self::$router->getBaseDir()); // Make sure also it gets the basic context
        } else {
            // Use either parameters or $_SERVER variables to determine the host to provide
            $scheme = array_pop_key("_scheme", $routeParameters) ?? $this->getContext()->getScheme();
            $baseDir = array_pop_key("_base_dir", $routeParameters) ?? $this->getContext()->getBaseUrl();
            $host = array_pop_key("_host", $routeParameters) ?? $this->getContext()->getHost();
            $port = array_pop_key("_port", $routeParameters) ?? explode(":", $host)[1] ?? $this->getContext()->getHttpPort();
            $host = explode(":", $host)[0] . ":" . $port;

            $parse = parse_url2(get_url($scheme, $host, $baseDir), -1, $baseDir);
            $parse["base_dir"] = $baseDir;
        }

        if (is_array($parse)) {
            if ($parse && array_key_exists("host", $parse)) {
                $this->getContext()->setHost($parse["host"]);
            }
            if ($parse && array_key_exists("base_dir", $parse)) {
                $this->getContext()->setBaseUrl($parse["base_dir"]);
            }

            $this->getContext()->setHttpPort(80);    // Port already included in host
            $this->getContext()->setHttpsPort(443); // Port already included in host
            $this->getContext()->setScheme($parse["scheme"] ?? "https");
            $this->getContext()->setQueryString($parse["query"] ?? "");
        }

        return $routeParameters;
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        //
        // Prevent to generate custom route with Symfony internal route.
        // NB: It breaks and gets infinite loop due to "_profiler*" route, if not set..
        if (str_starts_with($name, "_") || !self::$router->useAdvancedFeatures()) {
            $parameters = $this->resolveParameters($parameters);
            return parent::generate($name, $parameters, $referenceType);
        }

        //
        // Extract locale from route name if found
        foreach (self::$router->getLocalizer()->getAvailableLocaleLangs() as $lang) {
            if (str_ends_with($name, "." . $lang)) {
                $name = str_rstrip($name, "." . $lang);
                $locale = $lang;
            }
        }

        // Check whether the route is already cached
        $hash = self::$router->getRouteHash($name, $parameters, $referenceType);
        if (array_key_exists($hash, $this->cachedRoutes) && $this->cachedRoutes[$hash]["_name"] !== null && BaseBundle::USE_CACHE) {
            $cachedRoute = $this->cachedRoutes[$hash];

            $locale = array_key_exists("_locale", $parameters) ? self::$router->getLocalizer()->getLocaleLang($parameters["_locale"]) : self::$router->getLocalizer()->getLocaleLang();
            try {
                return $this->resolveUrl($cachedRoute["_name"] . ($locale ? "." . $locale : ""), $parameters, $referenceType);
            } catch (Exception $exception) {
                return $this->resolveUrl($cachedRoute["_name"], $parameters, $referenceType);
            }
        }

        $routeGroups = self::$router->getRouteGroups($name);
        $routeDefaultName = first($routeGroups);
        if (array_key_exists("_locale", $parameters)) {
            $locale = self::$router->getLocalizer()->getLocaleLang($parameters["_locale"]);
            if (!str_ends_with($name, "." . $locale)) {
                $routeDefaultName .= "." . self::$router->getLocalizer()->getLocaleLang($parameters["_locale"]);
            }
        }

        $routes = array_filter(array_transforms(fn($k, $name): array => [$name, self::$router->getRoute($name)], $routeGroups));

        //
        // Try to compute subgroup (if not found compute base)
        try {
            $routeUrl = $this->resolveUrl($name, $parameters, $referenceType);
        } catch (Exception $e) {
            if (str_starts_with($name, "app_")) {
                $name = "base_" . substr($name, 4);
                try {
                    $routeUrl = $this->resolveUrl($name, $parameters, $referenceType);
                } catch (Exception $_) {
                    throw $e;
                }
            } elseif ($name == $routeDefaultName || $routeDefaultName === null) {
                throw $e;
            }

            $name = $routeDefaultName;
            if ($name !== null) {
                $routeUrl = $this->resolveUrl($name, $parameters, $referenceType);
            }
        }

        $cache = self::$router->getCache();
        if ($cache !== null) {
            $routeRequirements = [];
            if (array_key_exists($name, $routes)) {
                $routeRequirements = $routes[$name]->getRequirements();
            }

            $this->cachedRoutes[$hash] = [
                "_name" => $name,
                "_requirements" => $routeRequirements
            ];

            $cache->save(self::$router->getCacheRoutes()->set($this->cachedRoutes));
        }

        return $routeUrl;
    }


    public function groups(?string $routeName): array
    {
        $routeName = explode(".", $routeName ?? "")[0];

        $routeNames = array_keys($this->getCompiledRoutes());
        $routeGroups = array_transforms(function ($k, $_routeName) use ($routeName): ?Generator {
            if ($_routeName !== $routeName && !str_starts_with($_routeName, $routeName . ".")) {
                return null;
            }

            $_routeNameWithoutLocale = str_rstrip($_routeName, "." . Localizer::getDefaultLocaleLang());
            if ($_routeName != $_routeNameWithoutLocale) {
                yield null => $_routeNameWithoutLocale;
            }

            yield null => $_routeName;
        }, $routeNames);

        return array_unique($routeGroups);
    }
}
