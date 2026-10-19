<?php

namespace Base\Contract;

/**
 * A route's address by a router's reference type, through the Urls contract
 * (plan "omnibase agnostique", C3e): what an entity's __toLink() made with
 * BaseTrait's router, `$this->getRouter()->generate($name, $parameters,
 * $referenceType)`, made where there is no framework too.
 *
 * Not a contract: a helper over Runtime::urls(). The entities' __toLink()
 * take Symfony's reference types (UrlGeneratorInterface's constants); their
 * values are named here, so that a link is made without the routing
 * component. An absolute path is Urls::path(), an absolute URL Urls::url():
 * the two the core, its bundles and the sites ask for. A relative path and a
 * network path have no equivalent in the contract; they are asked of the
 * Urls implementation's own generate($name, $parameters, $referenceType)
 * when it has one (omnibridge/symfony's Omnibridge\Symfony\RouterUrls: the router, as before),
 * and refused otherwise.
 */
final class Link
{
    /** Symfony's UrlGeneratorInterface::ABSOLUTE_URL: scheme, host and path */
    public const ABSOLUTE_URL = 0;

    /** Symfony's UrlGeneratorInterface::ABSOLUTE_PATH: the path from the root */
    public const ABSOLUTE_PATH = 1;

    /** Symfony's UrlGeneratorInterface::RELATIVE_PATH */
    public const RELATIVE_PATH = 2;

    /** Symfony's UrlGeneratorInterface::NETWORK_PATH: "//host/path" */
    public const NETWORK_PATH = 3;

    /**
     * @param array<string, mixed> $parameters
     */
    public static function to(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $urls = Runtime::urls();

        return match ($referenceType) {
            self::ABSOLUTE_PATH => $urls->path($name, $parameters),
            self::ABSOLUTE_URL => $urls->url($name, $parameters),
            default => method_exists($urls, 'generate')
                ? $urls->generate($name, $parameters, $referenceType)
                : throw new \InvalidArgumentException(sprintf('The Urls contract answers an absolute path or URL; %s cannot make reference type %d of route "%s".', get_debug_type($urls), $referenceType, $name)),
        };
    }
}
