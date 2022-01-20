<?php

namespace Base\Component\HttpFoundation;

use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class Referrer
{
    /** @var RequestStack */
    private $requestStack;

    /** @var RouterInterface */
    private $router;

    public function __toString() : string { return $this->getUrl(); }
    public function __construct(RequestStack $requestStack, RouterInterface $router, AssetExtension $assetExtension)
    {
        $this->requestStack = $requestStack;
        $this->router       = $router;
        $this->assetExtension = $assetExtension;
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

    public function getRoute(?string $path = null): string
    {
        if($path === null) return "";
        
        $baseDir = $this->getAsset("/");
        $path = parse_url($path, PHP_URL_PATH);
        if ($baseDir && strpos($path, $baseDir) === 0)
            $path = substr($path, strlen($baseDir));

        try { $routeMatch = $this->router->match($path); }
        catch (ResourceNotFoundException $e) { return ''; }

        $route = $routeMatch['_route'] ?? "";
        return $route;
    }

    public function getUrl() : string 
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) return "";

        // Default referrer
        $targetPath = $request->headers->get("referer"); // Yes, with the legendary misspelling.
        $targetRoute = $this->getRoute($targetPath);

        // Form target path fallback
        if(!$targetRoute) {
            $targetPath = $request->getSession()->get('_target_path');
            $targetRoute = $this->getRoute($targetPath);
        }

        // Form security target path fallbacks
        if(!$targetRoute) {
            $targetPath = $request->getSession()->get('_security.main.target_path');
            $targetRoute = $this->getRoute($targetPath);
        }

        if(!$targetRoute) {
            $targetPath = $request->getSession()->get('_security.account.target_path');
            $targetRoute = $this->getRoute($targetPath);
        }

        if(!$targetRoute) {
            $targetPath = $this->assetExtension->getAssetUrl("");
            $targetRoute = $this->getRoute($targetPath);
        }

        return $targetPath;
    }
}