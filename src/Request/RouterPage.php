<?php

namespace Base\Request;

/**
 * A Page answered by omnibase's router (omnibridge/symfony's AdvancedRouter, or any router with its
 * questions: getRouteName(), isAdmin(), isProfiler(), isUX(), isSecured()), plus what a router does not know -
 * the sign-in's own pages, the restriction's routes, the address asked.
 */
final class RouterPage implements Page
{
    /**
     * @param list<string>          $redirectsOnDeny
     * @param (\Closure(): bool)|null $isSecurity
     */
    public function __construct(private readonly object $router, private readonly string $uri = '', private readonly array $redirectsOnDeny = [], private readonly ?\Closure $isSecurity = null)
    {
    }

    public function isSecurity(): bool
    {
        return null !== $this->isSecurity && ($this->isSecurity)();
    }

    public function isProfiler(): bool
    {
        return (bool) $this->router->isProfiler();
    }

    public function isAdmin(): bool
    {
        return (bool) $this->router->isAdmin();
    }

    public function isUx(): bool
    {
        return (bool) $this->router->isUX();
    }

    public function isSecured(): bool
    {
        return (bool) $this->router->isSecured();
    }

    public function name(): ?string
    {
        return $this->router->getRouteName();
    }

    public function redirectsOnDeny(): array
    {
        return $this->redirectsOnDeny;
    }

    public function uri(): string
    {
        return $this->uri;
    }
}
