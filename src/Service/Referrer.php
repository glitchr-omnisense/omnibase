<?php

namespace Base\Service;

use Base\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use  Base\Security\LoginFormAuthenticator;
use  Base\Security\RescueFormAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 *
 */
class Referrer implements ReferrerInterface
{
    /** @var RequestStack */
    private RequestStack $requestStack;

    /** @var RouterInterface */
    private RouterInterface $router;

    public function __toString(): string
    {
        return $this->getUrl() ?? "";
    }

    public function __construct(RequestStack $requestStack, RouterInterface $router)
    {
        $this->requestStack = $requestStack;
        $this->router = $router;
    }

    public function redirect(array $headers = []): RedirectResponse
    {
        return $this->router->redirect($this->getUrl() ?? $this->router->getBaseDir(), [], 302, $headers);
    }

    /**
     * @param string|null $routeName
     * @return bool
     */
    public function isVetoed(?string $routeName)
    {
        if (!$routeName) {
            return false;
        }

        if ($this->router->isUX($routeName)) {
            return true;
        }

        if (RescueFormAuthenticator::isSecurityRoute($routeName)) {
            return true;
        }

        if (LoginFormAuthenticator::isSecurityRoute($routeName)) {
            return true;
        }

        return false;
    }

    public function clear()
    {
        $this->requestStack->getMainRequest()->getSession()->remove('_security.' . $this->router->getRouteFirewall()->getName() . '.target_path');
        $this->requestStack->getMainRequest()->getSession()->remove('_security.account.target_path'); // Internal definition by firewall
        $this->requestStack->getMainRequest()->getSession()->set('_target_path', null);
    }

    /**
     * @param string|null $url
     * @return $this
     */
    /**
     * @param string|null $url
     * @return $this
     */
    public function setUrl(?string $url)
    {
        if ($this->isVetoed($this->router->getRouteName($url))) {
            return $this;
        }
        
        $this->requestStack->getMainRequest()->getSession()->remove('_security.' . $this->router->getRouteFirewall()->getName() . '.target_path');
        $this->requestStack->getMainRequest()->getSession()->remove('_security.account.target_path'); // Internal definition by firewall
        $this->requestStack->getMainRequest()->getSession()->set('_target_path', $url);

        return $this;
    }

    public function sameSite(): bool
    {
        $currentHost = parse_url2(get_url())["host"] ?? null;
        $targetHost = parse_url2($this->getUrl())["host"] ?? $currentHost ?? null;

        return $currentHost == $targetHost;
    }

    public function getUrl(): ?string
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return null;
        }

        // Target path fallbacks
        $targetPath = $request->request->get('_target_path');
        $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
        $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;

        if (!$targetRoute) {
            $targetPath = $request->getSession()->get('_target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        // Security fallbacks
        if (!$targetRoute) {
            $targetPath = $request->getSession()->get('_security.main.target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        if (!$targetRoute) {
            $targetPath = $request->getSession()->get('_security.account.target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        // Default referrer
        if (!$targetRoute) {
            $targetPath = $request->headers->get("referer"); // Yes.. with the legendary misspelling.
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        if (!$targetRoute) {
            $targetPath = $request->request->get('referrer');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        return $targetRoute ? $targetPath : null;
    }
}
