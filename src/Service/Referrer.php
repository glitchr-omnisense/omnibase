<?php

namespace Base\Service;

use Base\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class Referrer implements ReferrerInterface
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

    public function setUrl(?string $url)
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
        $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
        if(!$targetRoute) {
            $targetPath = $request->getSession()->get('_target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
        }

        // Security fallbacks
        if(!$targetRoute) {
            $targetPath = $request->getSession()->get('_security.main.target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
        }
        if(!$targetRoute) {
            $targetPath = $request->getSession()->get('_security.account.target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
        }

        // Default referrer
        if(!$targetRoute) {
            $targetPath = $request->headers->get("referer"); // Yes.. with the legendary misspelling.
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
        }
        if(!$targetRoute) {
            $targetPath = $request->request->get('referer');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
        }

        if(!$targetRoute) {
            $targetPath = $request->request->get('referrer');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
        }

        return $targetPath ? $targetPath : null;
    }
}