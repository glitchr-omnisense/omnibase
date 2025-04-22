<?php

namespace Base\Routing\Generator;

use Base\BaseBundle;
use Base\Service\Localizer;
use Exception;
use Generator;
use Psr\Log\LoggerInterface;

use InvalidArgumentException;
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
        $this->context = $context;

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

    protected function resolveCandidates(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH): array
    {
        $routeCandidates = [];
        $locale = $routeParameters["_locale"] ?? self::$router->getLocalizer()->getLocaleLang(); 
    
        if (!str_ends_with($routeName, "." . $locale)) {
            $localizedAppRouteName = $routeName . "." . $locale;
            $routeCandidates[] = $localizedAppRouteName;
        }
    
        $routeCandidates[] = $routeName;
        $routeGroups = self::$router->getRouteGroups($routeName);

        $routeDefaultName = first($routeGroups);
        if (!str_ends_with($routeDefaultName, "." . $locale)) {
            $routeCandidates[] = $routeDefaultName . "." . $locale;
        }
    
        $routeCandidates[] = $routeDefaultName;

        if (str_starts_with($routeName, 'app_')) {
            $routeName = 'base_' . substr($routeName, 4);
    
            if (!str_ends_with($routeName, "." . $locale)) {
                $routeCandidates[] = $routeName . "." . $locale;
            }
    
            $routeCandidates[] = $routeName;

            $routeDefaultName = 'base_' . substr($routeDefaultName, 4);
            if (!str_ends_with($routeDefaultName, "." . $locale)) {
                $routeCandidates[] = $routeDefaultName . "." . $locale;
            }

            $routeCandidates[] = $routeDefaultName;
        }

        $routes = array_filter(array_transforms(fn($k, $routeName): array => [$routeName, self::$router->getRoute($routeName)], $routeGroups));
        ksort($routes);

        return $routes;
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

        return array_filter($routeParameters, fn($p) => $p !== null);
    }

    public function generate(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $referenceType = array_key_exists("_host", $routeParameters) && $routeParameters["_host"] !== null ? self::ABSOLUTE_URL : $referenceType;

        //
        // Prevent to generate custom route with Symfony internal route.
        // NB: It breaks and gets infinite loop due to "_profiler*" route, if not set..
        if (str_starts_with($routeName, "_") || !self::$router->useAdvancedFeatures()) {
            $routeParameters = $this->resolveParameters($routeParameters);
            return parent::generate($routeName, $routeParameters, $referenceType);
        }

        //
        // Extract locale from route name if found
        foreach (self::$router->getLocalizer()->getAvailableLocaleLangs() as $lang) {
            if (str_ends_with($routeName, "." . $lang)) {
                $routeName = str_rstrip($routeName, "." . $lang);
                $locale = $lang;
            }
        }

        // Update context, transforms requested route by adding parameters
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

                        if (is_string($routeParameter)) {
                            $routeParameters[$key] = str_replace($search, $replace, $routeParameter);
                        } else {
                            $routeParameters[$key] = $routeParameter;
                        }
                    }
                }

                $routeParameters = $this->resolveParameters($routeParameters);
            }
        }

        // Check whether the route is already cached
        $hash = self::$router->getRouteHash($routeName, $routeParameters, $referenceType);
        if (array_key_exists($hash, $this->cachedRoutes) && $this->cachedRoutes[$hash]["_name"] !== null && BaseBundle::USE_CACHE) {
            $cachedRoute = $this->cachedRoutes[$hash];

            $locale = array_key_exists("_locale", $routeParameters) ? self::$router->getLocalizer()->getLocaleLang($routeParameters["_locale"]) : self::$router->getLocalizer()->getLocaleLang();
            try {
                return parent::generate($cachedRoute["_name"] . ($locale ? "." . $locale : ""), $routeParameters, $referenceType);
            } catch (Exception $_) {
                return parent::generate($cachedRoute["_name"], $routeParameters, $referenceType);
            }
        }

        $routes = $this->resolveCandidates($routeName, $routeParameters, $referenceType);
        foreach($routes as $routeName => $route) {

            
            try {
                $routeUrl = parent::generate($routeName, $routeParameters, $referenceType);
            } catch(\Exception $_) {
                continue;
            }

            $cache = self::$router->getCache();
            if ($cache !== null) {
                
                $routeRequirements = [];
                if (array_key_exists($routeName, $routes)) {
                    $routeRequirements = $routes[$routeName]->getRequirements();
                }
                
                $this->cachedRoutes[$hash] = [
                    "_name" => $routeName,
                    "_requirements" => $routeRequirements
                ];
                
                $cache->save(self::$router->getCacheRoutes()->set($this->cachedRoutes));
            }
            
            return sanitize_url($routeUrl);
        }

        throw new RouteNotFoundException("No valid route found for `$routeName`. Candidate routes are ". implode(", ", array_keys($routes)). "(please check mandatory variables?)");
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
