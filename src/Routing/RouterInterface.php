<?php

namespace Base\Routing;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface as SymfonyRouterInterface;

interface RouterInterface extends SymfonyRouterInterface, RequestMatcherInterface, WarmableInterface
{
    public function isProfiler($request = null);
    public function isEasyAdmin($request = null);
    public function keepMachine(): bool;
    public function keepSubdomain(): bool;

    public function getRequest(): ?Request;
    public function getLang(?string $locale = null): string;
    public function getContext(): RequestContext;
    public function setContext(RequestContext $context);

    public function getGenerator(): UrlGeneratorInterface;
    public function getMatcher(): UrlMatcherInterface;

    public function isCli(): bool;
    public function isDebug(): bool;

    public function getCache();
    public function getCacheRoutes();

    public function getRoute(?string $routeNameOrUrl = null): ?Route;
    public function getRouteHash(string $routeNameOrUrl): string;
    public function getRouteName(?string $routeUrl = null): ?string;
    public function getRouteMatch(?string $routeUrl = null): ?array;
    public function getRouteGroups(string $routeName): array;
    public function getRouteFirewall(?string $routeUrl = null): ?string;

    public function redirect(string $urlOrRoute, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse;
    public function redirectToRoute(string $routeName, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse;
    public function reloadRequest(?Request $request = null): RedirectResponse;
}