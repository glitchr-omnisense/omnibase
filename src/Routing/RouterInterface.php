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
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Contracts\EventDispatcher\Event;

interface RouterInterface extends SymfonyRouterInterface, RequestMatcherInterface, WarmableInterface
{
    public function getRouteIndex(): string;
    public function getUrlIndex(): string;

    public function useAdvancedFeatures(): bool;
    public function isProfiler(mixed $request = null): bool;
    public function isEasyAdmin(mixed $request = null): bool;
    public function isUX(mixed $request = null): bool;
    public function isWdt(mixed $request = null): bool;
    public function isSecured(mixed $request = null): bool;
    public function keepMachine(): bool;
    public function keepSubdomain(): bool;
    public function keepDomain(): bool;

    public function getBaseDir(?string $locale = null, ?string $environment = null): string;
    public function getHost(?string $locale = null, ?string $environment = null): string;
    public function getHostFallback(?string $locale = null, ?string $environment = null): string;
    public function getMachine(?string $locale = null, ?string $environment = null): ?string;
    public function getMachineFallback(?string $locale = null, ?string $environment = null): ?string;
    public function getSubdomain(?string $locale = null, ?string $environment = null): ?string;
    public function getSubdomainFallback(?string $locale = null, ?string $environment = null): ?string;
    public function getDomain(?string $locale = null, ?string $environment = null): string;
    public function getDomainFallback(?string $locale = null, ?string $environment = null): string;
    public function getPort(?string $locale = null, ?string $environment = null): ?int;
    public function getPortFallback(?string $locale = null, ?string $environment = null): ?int;

    public function getUrl(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string;
    public function getAssetUrl(string $name, ?string $packageName = null): string;

    public function getRequest(): ?Request;
    public function getEnvironment(): string;
    public function getLocale(?string $locale = null): string;
    public function getLocaleLang(?string $lang = null): string;

    public function getContext(): RequestContext;
    public function setContext(RequestContext $context);

    public function getGenerator(): UrlGeneratorInterface;
    public function getMatcher(): UrlMatcherInterface;

    public function isCli(): bool;
    public function isDebug(): bool;
    public function isBackend(mixed $request = null): bool;
    public function hasFirewall(?string $routeUrl = null): ?bool;

    public function getCache();
    public function getCacheRoutes();

    public function getRoute(?string $routeNameOrUrl = null): ?Route;
    public function getRouteHash(string $routeNameOrUrl): string;
    public function getRouteName(?string $routeUrl = null): ?string;
    public function getRouteMatch(?string $routeUrl = null): ?array;
    public function getRouteGroups(string $routeName): array;
    public function getRouteFirewall(?string $routeUrl = null): ?FirewallConfig;

    public function redirect(string $urlOrRoute, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse;
    public function redirectToRoute(string $routeName, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse;
    public function redirectEvent(Event $event, string $routeName, array $routeParameters = [], int $state = 302, array $headers = []): bool;
    public function reloadRequest(?Request $request = null): RedirectResponse;
}
