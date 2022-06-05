<?php

namespace Base\Routing\Matcher;

use Exception;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;

class AdvancedUrlMatcher extends CompiledUrlMatcher implements RedirectableUrlMatcherInterface
{
    public function redirect(string $path, string $route, string $scheme = null): array
    {
        return [
            '_controller' => 'Symfony\\Bundle\\FrameworkBundle\\Controller\\RedirectController::urlRedirectAction',
            'path' => $path,
            'permanent' => true,
            'scheme' => $scheme,
            'httpPort' => $this->context->getHttpPort(),
            'httpsPort' => $this->context->getHttpsPort(),
            '_route' => $route,
        ];
    }

    public function match(string $pathinfo): array
    {
        $parse = parse_url2() ?? [];
        $this->getContext()->setHost($parse["host"] ?? "");

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

        $parse = parse_url2() ?? [];
        $parse = array_merge($parse, $parsePathinfo);

        $this->getContext()->setHost($parse["host"] ?? "");
        return parent::match($parse["path"] ?? $pathinfo);
    }
}
