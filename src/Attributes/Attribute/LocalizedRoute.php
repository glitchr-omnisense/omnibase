<?php

namespace Base\Attributes\Attribute;

/**
 * A plain Symfony Route attribute plus $translationKey support — unlike
 * Route (this bundle's other Route subclass), it does NOT compose any
 * host/domain/subdomain/machine templating, so it's safe as a drop-in
 * replacement for Symfony's own #[Route] on any controller, with no change
 * to host matching. Use Route instead when you specifically need its
 * multi-tenant host templating (see ShortLinkController for an example).
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class LocalizedRoute extends \Symfony\Component\Routing\Attribute\Route
{
    use RoutePathDictionaryTrait;

    public function __construct(
        string|array|null $path = null,
        ?string           $name = null,
        array             $requirements = [],
        array             $options = [],
        array             $defaults = [],
        ?string           $host = null,
        array|string      $methods = [],
        array|string      $schemes = [],
        ?string           $condition = null,
        ?int              $priority = null,
        ?string           $locale = null,
        ?string           $format = null,
        ?bool             $utf8 = null,
        ?bool             $stateless = null,
        ?string           $env = null,
        ?string           $translationKey = null
    )
    {
        if ($translationKey !== null) {
            $path = self::resolveTranslationKey($translationKey);
        }

        parent::__construct($path, $name, $requirements, $options, $defaults, $host, $methods, $schemes, $condition, $priority, $locale, $format, $utf8, $stateless, $env);
    }
}
