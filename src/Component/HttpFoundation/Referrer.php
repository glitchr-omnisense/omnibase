<?php

namespace Base\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class Referrer
{
    /** @var RequestStack */
    private $requestStack;

    /** @var RouterInterface */
    private $router;

    public function __toString() : string { return $this->getUrl() ?? ""; }
    public function __construct(RequestStack $requestStack, RouterInterface $router)
    {
        $this->requestStack = $requestStack;
        $this->router       = $router;
    }

    public function getAsset(string $url): string
    {
        $url = trim($url);
        $parseUrl = parse_url($url);
        if($parseUrl["scheme"] ?? false)
            return $url;

        $request = $this->requestStack->getCurrentRequest();
        $baseDir = $request ? $request->getBasePath() : $_SERVER["CONTEXT_PREFIX"] ?? "";

        $path = trim($parseUrl["path"]);
        if($path == "/") return $baseDir;
        else if(!str_starts_with($path, "/"))
            $path = $baseDir."/".$path;

        return $path;
    }

    public function getRouteName(?string $path = null, ?string $requestUri = null): string
    {
        if($path === null) return "";

        $baseDir = $this->getAsset("/");
        $path = parse_url($path, PHP_URL_PATH);
        if($path === $requestUri) return "";

        if ($baseDir && strpos($path, $baseDir) === 0)
            $path = mb_substr($path, strlen($baseDir));

        try { $routeMatch = $this->router->match($path); }
        catch (ResourceNotFoundException $e) { return ''; }

        $route = $routeMatch['_route'] ?? "";

        return $route;
    }

    public function setUrl(string $url)
    {
        $this->requestStack->getMainRequest()->getSession()->set('_target_path', $url);
        return $this;
    }

    public function getUrl() : ?string 
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) return null;

        // Target path fallbacks
        $targetPath = $request->request->get('_target_path');
        $targetRoute = $this->getRouteName($targetPath, $request->getRequestUri());

        if(!$targetRoute) {
            $targetPath = $request->getSession()->get('_target_path');
            $targetRoute = $this->getRouteName($targetPath, $request->getRequestUri());
        }

        // Security fallbacks
        if(!$targetRoute) {
            $targetPath = $request->getSession()->get('_security.main.target_path');
            $targetRoute = $this->getRouteName($targetPath, $request->getRequestUri());
        }
        if(!$targetRoute) {
            $targetPath = $request->getSession()->get('_security.account.target_path');
            $targetRoute = $this->getRouteName($targetPath, $request->getRequestUri());
        }

        // Default referrer
        if(!$targetRoute) {
            $targetPath = $request->headers->get("referer"); // Yes, with the legendary misspelling.
            $targetRoute = $this->getRouteName($targetPath, $request->getRequestUri());
        }
        if(!$targetRoute) {
            $targetPath = $request->request->get('referer');
            $targetRoute = $this->getRouteName($targetPath, $request->getRequestUri());
        }
        if(!$targetRoute) {
            $targetPath = $request->request->get('referrer');
            $targetRoute = $this->getRouteName($targetPath, $request->getRequestUri());
        }

        return $targetPath;
    }
}