<?php

namespace Base\Routing\Generator;

use Base\Service\LocaleProvider;
use Base\Traits\BaseTrait;
use Exception;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;

class AdvancedUrlGenerator extends CompiledUrlGenerator
{
    use BaseTrait;

    protected function resolve(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH): ?string
    {
        // Transforms requested route by adding parameters
        if($routeName === null) return null;
        if(($route = $this->getRouter()->getRouteCollection()->get($routeName))) {

            if($route->getHost()) $referenceType = self::ABSOLUTE_URL;

            if(preg_match_all("/{(\w*)}/", $route->getHost().$route->getPath(), $matches)) {

                $url = parse_url2(get_url());

                $parameterNames = array_flip($matches[1]);
                $routeParameters = array_merge(
                    array_intersect_key($url, $parameterNames),
                    $route->getDefaults(),
                    $routeParameters,
                );

                $search  = array_map(fn($k) => "{".$k."}", array_keys($url));
                $replace = array_values($url);
                foreach($routeParameters as &$routeParameter)
                    $routeParameter = str_replace($search, $replace, $routeParameter);
            }
        }

        if (!empty($routeName)) {

            try { return parent::generate($routeName, $routeParameters, $referenceType); }
            catch (RouteNotFoundException $e) { }

            try { return parent::generate($routeName.".".LocaleProvider::getDefaultLang(), $routeParameters, $referenceType); }
            catch (RouteNotFoundException $e) { return null; }
        }

        $request = $this->getRouter()->getRequest();
        if(!$request) return null;

        $routeName = $request->get('_route');

        return $routeName ? parent::generate($routeName, [], $referenceType) : null;
    }

    public function generate(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        // Symfony internal root, I assume.. Infinite loop due to "_profiler*" route, if not set
        if(str_starts_with($routeName, "_")) {
        
            try {return $this->resolve($routeName, $routeParameters, $referenceType); }
            catch (Exception $e ) { throw $e; }
        }

        // Handle CLI case using either $_SERVER variables,
        // or base settting database information, if available.
        $baseDir = null;
        switch($referenceType) {

            case self::ABSOLUTE_URL:
                $baseDir    = $this->getSettings()->url("/", null, $referenceType);
                break;

            case self::NETWORK_PATH:
                $baseDir    = $this->getSettings()->base_dir();
                $baseDir    = "//".trim($baseDir, "/");
                break;

            case self::RELATIVE_PATH:
                $baseDir    = ".";
                break;

            case self::ABSOLUTE_PATH:
                $baseDir    = $this->getSettings()->base_dir();
                $baseDir    = str_rstrip($baseDir, "/");
        }

        // Implement route subgroup to improve connectivity
        // between logical routes in case of multiple @Route annotations
        $currentRouteName = explode(".", $this->getRouter()->getRouteName());
        $routeName = explode(".", $routeName) ?? $currentRouteName;

        $routeGroup = count($routeName) > 1 ? tail($routeName) : null;
        $routeGroup = $routeGroup ?? count($currentRouteName) > 1 ? tail($currentRouteName) : null;
        $routeGroup = $routeGroup ?? [];
        $routeGroup = $routeGroup ? ".".implode(".",$routeGroup) : null;

        $routeBase = $routeName[0];
        $routeName = $routeBase.$routeGroup;
        if(!$routeName) $routeName = $this->getRouter()->getRouteName($this->resolve($routeBase, $routeParameters));

        // Prepare the default route if not found.
        // In case a group doesn't exists, it will be replaced by the first group found in the route collection list.
        $routeGroups = $this->getRouter()->getRouteGroups($routeName);

        $routeDefaultGroup = first($routeGroups);
        $routeDefaultName = $routeBase.($routeDefaultGroup ? ".".$routeDefaultGroup : "");
        if(!$routeDefaultName) $routeDefaultName = $this->getRouter()->getRouteName($this->resolve($routeBase, $routeParameters));

        //
        // Strip unused variables from main group
        $routeUrl      = $this->resolve($routeBase, $routeParameters);
        $routeGroupUrl = $this->resolve($routeBase.$routeGroup, $routeParameters);
        if($routeGroupUrl !== null && $routeUrl !== null) {

            $keys = array_keys(array_diff_key($this->getRouter()->getRouteParameters($routeUrl), $this->getRouter()->getRouteParameters($routeGroupUrl)));
            $routeParameters = array_key_removes($routeParameters, ...$keys);
        }
        
        // Try to compute subgroup (or base one)
        $routeUrl ??= $this->resolve($routeName, $routeParameters, $referenceType);
        $routeUrl ??= $this->resolve($routeDefaultName, $routeParameters, $referenceType);

        // Clean up double slashes..
        $parts = filter_var($routeUrl, FILTER_VALIDATE_URL) ? parse_url($routeUrl) : ["path" => $routeUrl];
        $parts["path"] = "/".str_strip(str_replace("//", "/", $parts["path"]), "/");
        $routeUrl = build_url($parts);

        if(str_starts_with($routeUrl, "http")) return $routeUrl;
        return str_rstrip($baseDir."/".str_lstrip($routeUrl, "/"), "/");
    }
}
