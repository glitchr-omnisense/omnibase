<?php

namespace Base\Service;

use Base\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use  Base\Security\LoginFormAuthenticator;
use  Base\Security\RescueFormAuthenticator;

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

    public function isVetoed(?string $routeName) 
    {
        if(!$routeName) return false;
        if(RescueFormAuthenticator::isSecurityRoute($routeName))
            return true;

        if(LoginFormAuthenticator::isSecurityRoute($routeName))
            return true;
    }
    
    public function setUrl(?string $url)
    {
        $route = $url ? $this->router->getRouteName($url) : null;

        $this->requestStack->getMainRequest()->getSession()->set('_target_path', $url);
        return $this;
    }

    public function sameSite() : bool
    {
        $currentHost = parse_url2(get_url())["host"] ?? null;
        $targetHost  = parse_url2($this->getUrl())["host"] ?? $currentHost ?? null;

        return $currentHost == $targetHost;
    }

    public function getUrl() : ?string
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) return null;

        // Target path fallbacks
        $targetPath = $request->request->get('_target_path');
        $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
        $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;

        if(!$targetRoute && !$this->isVetoed($targetRoute)) {
            $targetPath = $request->getSession()->get('_target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        // Security fallbacks
        if(!$targetRoute) {
            $targetPath = $request->getSession()->get('_security.main.target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        if(!$targetRoute) {
            $targetPath = $request->getSession()->get('_security.account.target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        // Default referrer
        if(!$targetRoute) {
            $targetPath = $request->headers->get("referer"); // Yes.. with the legendary misspelling.
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        if(!$targetRoute) {
            $targetPath = $request->request->get('referer');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        if(!$targetRoute) {
            $targetPath = $request->request->get('referrer');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        return $targetPath ? $targetPath : null;
    }
}