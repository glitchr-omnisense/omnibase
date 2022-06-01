<?php

namespace Base\Routing\Generator;

use Base\Service\LocaleProvider;
use Base\Traits\BaseTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\RequestContext;

class AdvancedUrlGenerator extends CompiledUrlGenerator
{
    use BaseTrait;

    public function __construct(array $compiledRoutes, RequestContext $context, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        // NB: This generator needs separate context as it calls its corresponding Matcher..
        //     (.. and Matcher class is changing context.)
        $context = new RequestContext($context->getBaseUrl(), $context->getMethod(), $context->getHost(), $context->getScheme(), $context->getHttpPort(), $context->getHttpsPort(), $context->getPathInfo(), $context->getQueryString());
        parent::__construct($compiledRoutes, $context, $logger, $defaultLocale);
    }

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
                foreach($routeParameters as $key => &$routeParameter) {
                    $routeParameter = str_replace($search, $replace, $routeParameter);
                    if($key == "host") $routeParameter = str_lstrip($routeParameter, "www.");
                }
            }
        }

        if (!empty($routeName)) {

            try { return parent::generate($routeName, $routeParameters, $referenceType); }
            catch (RouteNotFoundException $e) { }

            return parent::generate($routeName.".".LocaleProvider::getDefaultLang(), $routeParameters, $referenceType);
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

            try { return $this->resolve($routeName, $routeParameters, $referenceType); }
            catch (Exception $e ) { throw $e; }
        }

        //
        // Use either parameters or $_SERVER variables to determine the host to provide
        $url = parse_url2(get_url(true, true,
            array_pop_key("_scheme", $routeParameters) ?? $this->getSettings()->scheme(),
            array_pop_key("_host", $routeParameters) ?? $this->getSettings()->host() ,
            array_pop_key("_base_dir", $routeParameters) ?? $this->getSettings()->base_dir(),
        ));

        if(array_key_exists("host", $url))
            $this->getContext()->setHost($url["host"]);
        if(array_key_exists("base_dir", $url))
            $this->getContext()->setBaseUrl($url["base_dir"]);

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
        if(!$routeName) $routeName = $this->getRouter()->getRouteName($this->resolve($routeBase, $routeParameters, $referenceType));

        // Prepare the default route if not found.
        // In case a group doesn't exists, it will be replaced by the first group found in the route collection list.
        $routeGroups = $this->getRouter()->getRouteGroups($routeName);

        $routeDefaultGroup = first($routeGroups);
        $routeDefaultName = $routeBase.($routeDefaultGroup ? ".".$routeDefaultGroup : "");
        if(!$routeDefaultName) $routeDefaultName = $this->getRouter()->getRouteName($this->resolve($routeBase, $routeParameters, $referenceType));

        //
        // Strip unused variables from main group
        try { $routeUrl      = $this->resolve($routeBase, $routeParameters, $referenceType); }
        catch(Exception $e) { $routeUrl = null; }
        try { $routeGroupUrl = $this->resolve($routeBase.$routeGroup, $routeParameters, $referenceType); }
        catch(Exception $e) { $routeGroupUrl = null; }

        if($routeGroupUrl !== null && $routeUrl !== null) {

            $keys = array_keys(array_diff_key($this->getRouter()->getRouteParameters($routeUrl), $this->getRouter()->getRouteParameters($routeGroupUrl)));
            $routeParameters = array_key_removes($routeParameters, ...$keys);
        }

        //
        // Try to compute subgroup (or base one)
        try { return $this->resolve($routeName, $routeParameters, $referenceType); }
        catch(Exception $e) { if ($routeDefaultName == $routeName) throw $e; }

        return $this->resolve($routeDefaultName, $routeParameters, $referenceType);
    }
}