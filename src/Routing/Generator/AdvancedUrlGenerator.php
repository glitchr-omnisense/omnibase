<?php

namespace Base\Routing\Generator;

use Base\Security\LoginFormAuthenticator;
use Base\Security\RescueFormAuthenticator;
use Base\Service\LocaleProvider;
use Base\Traits\BaseTrait;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;

class AdvancedUrlGenerator extends CompiledUrlGenerator
{
    use BaseTrait;

    protected $compiledRoutes;
    public function getCompiledRoutes():array { return $this->compiledRoutes; }
    public function __construct(array $compiledRoutes, RequestContext $context, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        // NB: This generator needs separate context as it internally calls its corresponding Matcher in generates..
        //     (.. and Matcher class is changing context.)
        $context = new RequestContext($context->getBaseUrl(), $context->getMethod(), $context->getHost(), $context->getScheme(), $context->getHttpPort(), $context->getHttpsPort(), $context->getPathInfo(), $context->getQueryString());
        parent::__construct($compiledRoutes, $context, $logger, $defaultLocale);

        $this->compiledRoutes = $compiledRoutes;
    }

    protected function resolveUrl(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH): ?string
    {
        // Transforms requested route by adding parameters
        if($routeName === null) return null;

        if(($route = $this->getRouter()->getRoute($routeName))) {

            if($route->getHost()) $referenceType = self::ABSOLUTE_URL;
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
                    $routeParameter = str_replace($search, $replace, $routeParameter);
                    if($key == "host") $routeParameter = str_lstrip($routeParameter, "www.");
                }
            }
        }

        if (!empty($routeName)) {

            try { return parent::generate($routeName, $routeParameters, $referenceType); }
            catch (RouteNotFoundException $e) { }

            try { return parent::generate($routeName.".".LocaleProvider::getDefaultLang(), $routeParameters, $referenceType); }
            catch (RouteNotFoundException $e) { throw $e; }

        }

        $request = $this->getRouter()->getRequest();
        if(!$request) return null;

        $routeName = $request->get('_route');
        return $routeName ? parent::generate($routeName, [], $referenceType) : null;
    }

    public function resolveParameters(?array $routeParameters = null)
    {
        if($routeParameters !== null) {

            // Use either parameters or $_SERVER variables to determine the host to provide
            $parse = parse_url2(get_url(
                array_pop_key("_scheme", $routeParameters) ?? $this->getSettingBag()->scheme(),
                array_pop_key("_host", $routeParameters) ?? $this->getSettingBag()->host() ,
                array_pop_key("_base_dir", $routeParameters) ?? $this->getSettingBag()->base_dir(),
            ));

            if($parse && array_key_exists("host", $parse))
                $this->getContext()->setHost($parse["host"]);
            if($parse && array_key_exists("base_dir", $parse))
                $this->getContext()->setBaseUrl($parse["base_dir"]);

        } else {

            $parse = parse_url2(get_url()); // Make sure also it gets the basic context
            if($parse && array_key_exists("host", $parse))
                $this->getContext()->setHost($parse["host"]);
            if($parse && array_key_exists("base_dir", $parse))
                $this->getContext()->setBaseUrl($parse["base_dir"]);
        }

        return $routeParameters;
    }

    public function generate(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        //
        // Prevent to generate custom route with Symfony internal route.
        // NB: It breaks and gets infinite loop due to "_profiler*" route, if not set..
        if(str_starts_with($routeName, "_")) {

            $routeParameters = $this->resolveParameters() ?? $routeParameters;
            try { return parent::generate($routeName, $routeParameters, $referenceType); }
            catch (Exception $e ) { throw $e; }
        }

        //
        // Update context
        $routeParameters = $this->resolveParameters($routeParameters);

        // Implement route subgroup to improve connectivity
        // between logical routes in case of multiple @Route annotations
        $currentRouteName = $this->getRouter()->getRouteName();
        $currentRouteName = $currentRouteName ? explode(".", $this->getRouter()->getRouteName()) : [];
        $routeName = explode(".", $routeName) ?? $currentRouteName;

        $routeGroup = count($routeName) > 1 ? tail($routeName) : null;
        $routeGroup = $routeGroup ?? (count($currentRouteName) > 1 ? tail($currentRouteName) : null);
        $routeGroup = $routeGroup ?? [];
        $routeGroup = $routeGroup ? ".".implode(".",$routeGroup) : null;

        $routeBase = $routeName[0];
        $routeName = $routeBase.$routeGroup;
        if(!$routeName) $routeName = $this->getRouter()->getRouteName($this->resolveUrl($routeBase, $routeParameters, $referenceType));

        // Prepare the default route if not found.
        // In case a group doesn't exists, it will be replaced by the first group found in the route collection list.
        $routeGroups = $this->getRouter()->getRouteGroups($routeName);

        $routeDefaultGroup = first($routeGroups);
        $routeDefaultName = $routeBase.($routeDefaultGroup ? ".".$routeDefaultGroup : "");
        if(!$routeDefaultName) $routeDefaultName = $this->getRouter()->getRouteName($this->resolveUrl($routeBase, $routeParameters, $referenceType));

        //
        // Strip unused variables from main group
        try { $routeUrl      = $this->resolveUrl($routeBase, $routeParameters, $referenceType); }
        catch(Exception $e) { $routeUrl = null; }

        try { $routeGroupUrl = $this->resolveUrl($routeBase.$routeGroup, $routeParameters, $referenceType); }
        catch(Exception $e) { $routeGroupUrl = null; }

        if($routeGroupUrl !== null && $routeUrl !== null) {

            $keys = array_keys(array_diff_key($this->getRouter()->getRouteDefaults($routeUrl), $this->getRouter()->getRouteDefaults($routeGroupUrl)));
            $routeParameters = array_key_removes($routeParameters, ...$keys);
        }


        //
        // Try to compute subgroup (or base one)
        try { return $this->resolveUrl($routeName, $routeParameters, $referenceType); }
        catch(Exception $e) { if ($routeDefaultName == $routeName) throw $e; }

        return $this->resolveUrl($routeDefaultName, $routeParameters, $referenceType);
    }

    public function format(string $url): string {

        $parse = parse_url2($url);

        $allowedSubdomain = false;
        $permittedSubdomains = $this->getParameterBag()->get("base.host_restriction.permitted_subdomains") ?? [];
        if(!$this->getRouter()->keepMachine() && !$this->getRouter()->keepSubdomain())
            $permittedSubdomains = "^$"; // Special case if both subdomain and machine are unallowed

        foreach($permittedSubdomains as $permittedSubdomain)
            $allowedSubdomain |= preg_match("/".$permittedSubdomain."/", $parse["subdomain"] ?? null);

        // Special case for login form.. to be redirected to rescue authenticator if no access right
        $routeName = $this->getRouter()->getRouteName();
        if(!LoginFormAuthenticator::isSecurityRoute($routeName) && !RescueFormAuthenticator::isSecurityRoute($routeName))
        {
            // Special case for WWW subdomain
            if(!array_key_exists("subdomain", $parse) && !array_key_exists("machine", $parse) && !$allowedSubdomain) {
                $parse["subdomain"] = "www";
            } else if( array_key_exists("subdomain", $parse) && !$allowedSubdomain) {

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
