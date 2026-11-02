<?php

namespace Base\Service;

use Base\Routing\AdvancedRouterInterface;
use Base\Http\RequestsOf;
use Omnibridge\Contract\Requests;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use  Base\Security\LoginFormAuthenticator;
use  Base\Security\RescueFormAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Where to go back to: the target path posted or kept in the session, the
 * firewall's, the referer. The request and its session through
 * Omnibridge\Contract\Requests (plan "omnibase agnostic", F5c).
 */
class Referrer implements ReferrerInterface
{
    private ?Requests $requests;

    /** @var AdvancedRouterInterface */
    private AdvancedRouterInterface $router;

    public function __toString(): string
    {
        return $this->getUrl() ?? "";
    }

    /** @param Requests|RequestStack|null $requests Symfony's RequestStack is deprecated (until 6.0) */
    public function __construct(#[Autowire(service: Requests::class)] Requests|RequestStack|null $requests, AdvancedRouterInterface $router)
    {
        $this->requests = RequestsOf::of($requests);
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
        $this->requests?->forget('_security.' . $this->router->getRouteFirewall()->getName() . '.target_path');
        $this->requests?->forget('_security.account.target_path'); // Internal definition by firewall
        $this->requests?->remember('_target_path', null);
    }

    /**
     * @param string|null $url
     * @return $this
     */
    public function setUrl(?string $url)
    {
        if ($this->isVetoed($this->router->getRouteName($url))) {
            return $this;
        }
        
        $this->requests?->forget('_security.' . $this->router->getRouteFirewall()->getName() . '.target_path');
        $this->requests?->forget('_security.account.target_path'); // Internal definition by firewall
        $this->requests?->remember('_target_path', $url);

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
        if (null === $this->requests || !$this->requests->has()) {
            return null;
        }
        $posted = $this->requests->request()?->request;

        // Target path fallbacks
        $targetPath = $posted?->get('_target_path');
        $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
        $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;

        if (!$targetRoute) {
            $targetPath = $this->requests->session('_target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        // Security fallbacks
        if (!$targetRoute) {
            $targetPath = $this->requests->session('_security.main.target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        if (!$targetRoute) {
            $targetPath = $this->requests->session('_security.account.target_path');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        // Default referrer
        if (!$targetRoute) {
            $targetPath = $this->requests->header("referer"); // Yes.. with the legendary misspelling.
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        if (!$targetRoute) {
            $targetPath = $posted?->get('referrer');
            $targetRoute = $targetPath ? $this->router->getRouteName($targetPath) : null;
            $targetRoute = !$this->isVetoed($targetRoute) ? $targetRoute : null;
        }

        // Host stripping
        $targetUrl = parse_url2($targetPath);

        $currentHost = parse_url2(get_url())["host"] ?? null;
        $targetHost = $targetUrl["host"] ?? $currentHost ?? null;
        if($currentHost == $targetHost) $targetUrl["host"] = null;

        // Never credentials ("user:password@"), whatever the stored address held
        if (is_array($targetUrl)) {
            unset($targetUrl["user"], $targetUrl["pass"], $targetUrl["password"]);
        }

        $targetPath = compose_url($targetUrl);
        return $targetRoute ? $targetPath : null;
    }
}
