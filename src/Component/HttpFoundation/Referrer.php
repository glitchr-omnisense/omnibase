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

    public function __construct(RequestStack $requestStack, RouterInterface $router, AssetExtension $assetExtension)
    {
        $this->requestStack = $requestStack;
        $this->router       = $router;
        $this->assetExtension = $assetExtension;
    }

    public function getRouteName(): string
    {
        $uri = strval($this);

        try { $routeMatch = $this->router->match($uri); }
        catch (ResourceNotFoundException $e) { return ''; }

        $route = $routeMatch['_route'] ?? "";
        return $route;
    }

    public function __toString() : string
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) return "";

        $uri = (string) $request->headers->get('referer'); // Yes, with the legendary misspelling.
        return empty($uri) ? $this->assetExtension->getAssetUrl("") : $uri;
    }

}