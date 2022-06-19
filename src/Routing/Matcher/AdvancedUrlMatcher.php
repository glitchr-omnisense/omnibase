<?php

namespace Base\Routing\Matcher;

use Base\Routing\Generator\AdvancedUrlGenerator;
use Base\Service\LocaleProvider;
use Base\Traits\BaseTrait;
use Exception;
use Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;

class AdvancedUrlMatcher extends CompiledUrlMatcher implements RedirectableUrlMatcherInterface
{
    use BaseTrait;

    protected $compiledRoutes;
    public function getCompiledRoutes():array { return $this->compiledRoutes; }

    public function __construct(array $compiledRoutes, RequestContext $context)
    {
        parent::__construct($compiledRoutes, $context);
        $this->compiledRoutes = $compiledRoutes;
    }

    public function redirect(string $path, string $route, string $scheme = null): array
    {
        return [
            '_controller' => 'Symfony\\Bundle\\FrameworkBundle\\Controller\\RedirectController::urlRedirectAction',
            '_route'      => $route,

            'path'        => $path,
            'permanent'   => true,
            'scheme'      => $scheme,
            'httpPort'    => $this->context->getHttpPort(),
            'httpsPort'   => $this->context->getHttpsPort(),
        ];
    }

    public function path(array $array): ?string
    {
        $path = "";
        $parameters = array_reverse($array ?? []);
        foreach($parameters as $parameter) {

            $path .= $parameter[1];
            $path .= $parameter[3] ?? false ? "{".$parameter[3]."}" : "";
        }

        return $path;
    }

    public function groups(?string $routeName): array
    {

        $generator = $this->getRouter()->getGenerator();
        if ($generator instanceof AdvancedUrlGenerator)
            $routeNames = array_keys($generator->getCompiledRoutes());

        // The next line should never be triggered as the generator is overloaded in __constructor
        $routeNames ??= array_keys($this->getRouter()->getRouteCollection()->all());

        $routeName = explode(".", $routeName ?? "")[0];
        $routeGroups = array_transforms(function($k,$_routeName) use ($routeName) : ?Generator {

            if($_routeName !== $routeName && !str_starts_with($_routeName, $routeName."."))
                return null;

            $_routeNameWithoutLocale = str_rstrip($_routeName, ".".LocaleProvider::getDefaultLang());
            if($_routeName != $_routeNameWithoutLocale)
                yield null => $_routeNameWithoutLocale;

            yield null => $_routeName;

        }, $routeNames);

        return array_unique($routeGroups);
    }

    public function firewall(string $pathinfo): ?string
    {
        return $this->getFirewallMap()->getFirewallConfig(Request::create($pathinfo))->getName();
    }

    public function match(string $pathinfo): array
    {
        //
        // Prevent to match custom route with Symfony internal route.
        // NB: It breaks and gets infinite loop due to "_profiler*" route, if not set..
        try { $match = parent::match($pathinfo); }
        catch (Exception $e) { $match = []; }
        if(array_key_exists("_route", $match))
            if(str_starts_with($match["_route"], "_")) return $match;

        //
        // Custom match implementation
        $parsePathinfo = parse_url2($pathinfo);
        if($parsePathinfo === false) return $match;

        $parse = parse_url2(get_url()) ?? [];
        $parse = array_merge($parse, $parsePathinfo);
        $this->getContext()->setHost($parse["host"] ?? "");

        try { return parent::match($parse["path"] ?? $pathinfo); }
        catch(Exception $e) { throw $e; }
    }
}
