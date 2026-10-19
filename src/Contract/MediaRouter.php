<?php

namespace Base\Contract;

/**
 * The router as omnibase's media rule asks it (audit "lesringostars face au
 * master plan", R6): FileService and MediaService - the image route's
 * obfuscated addresses, their resolution, the files they serve - and
 * Base\Service\ImageRoute, the route's answers, need only these five
 * questions of a router: an address of a named route, the route and the
 * parameters an address matches, the route's name of an address, the public
 * address of a file, an address by name or as it is.
 *
 * Under Symfony, omnibase's AdvancedRouterInterface extends it: the
 * advanced_router service is passed as before, the same answers. A host
 * without the framework that serves omnibase's images gives its own over its
 * router, on the image routes declared in Base\Service\ImageRoute::ROUTES
 * (omnibridge/laravel's, over Laravel's router).
 *
 * The reference types are those of Symfony's UrlGeneratorInterface and of
 * Base\Contract\Link: 0 an absolute URL, 1 an absolute path (the default),
 * 2 a relative path, 3 a network path. No constant is declared here, so that
 * AdvancedRouterInterface inherits these methods from both interfaces.
 */
interface MediaRouter
{
    /**
     * The address of a named route (ux_image, ux_imageExtension...) with its
     * parameters, by its reference type.
     *
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = 1): string;

    /**
     * What an address matches: its route's name under "_route" and the
     * route's parameters ("data", "extension"...); null when it matches none.
     *
     * @return array<string, mixed>|null
     */
    public function getRouteMatch(?string $routeUrl = null): ?array;

    /** The name of the route an address matches, null for none. */
    public function getRouteName(?string $routeUrl = null): ?string;

    /** A public file's address ("" is the public root's, "/" for most hosts). */
    public function getAssetUrl(string $nameOrUrl, ?string $packageName = null): string;

    /**
     * An address by a route's name, or an address as it is, by its reference
     * type.
     *
     * @param array<string, mixed> $parameters
     */
    public function getUrl(?string $nameOrUrl = null, array $parameters = [], int $referenceType = 1): string;
}
