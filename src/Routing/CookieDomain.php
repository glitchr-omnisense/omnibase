<?php

namespace Base\Routing;

/**
 * The domain a cookie of the whole site is written for, framework-free (step
 * R29 of the audit "lesringostars face au master plan"): omnibase's rule,
 * AdvancedRouter::getDomain()'s - the request's domain when it is one of the
 * configured domains (base.router.host_fallbacks), else the first of them;
 * "localhost" when the router uses no fallbacks. Its subdomains share the
 * cookie (a cookie with a Domain attribute). Symfony's router and
 * omnibridge/laravel's sign-in effects (the browser's PHPUSERID) write it the
 * same way.
 */
final class CookieDomain
{
    /**
     * @param string       $url        the address asked (its domain is read as omnibase's parse_url2() reads it: "symfony.test" of "lesringostars.symfony.test")
     * @param list<string> $configured the configured domains (domains())
     */
    public static function of(string $url, array $configured): ?string
    {
        $configured = [] !== $configured ? $configured : ['localhost'];
        $domain = parse_url2($url)['domain'] ?? null;
        if (!\in_array($domain, $configured)) {
            $first = first($configured);

            return \is_string($first) && '' !== $first ? $first : null;
        }

        return \is_string($domain) ? $domain : null;
    }

    /**
     * The configured domains of a language, as the router picks its fallbacks: the first
     * of base.router.host_fallbacks whose locale is the first of $locales to have one,
     * else the first without a locale; none when the router uses no fallbacks.
     *
     * @param array<int|string, mixed> $hostFallbacks base.router.host_fallbacks
     * @param list<string|null>        $locales       the locale, its language, the default locale, its language
     *
     * @return list<string>
     */
    public static function domains(bool $useFallbacks, array $hostFallbacks, array $locales): array
    {
        if (!$useFallbacks) {
            return [];
        }

        foreach ([...array_filter($locales, 'is_string'), null] as $locale) {
            foreach ($hostFallbacks as $fallback) {
                if (\is_array($fallback) && ($fallback['locale'] ?? null) === $locale) {
                    $domains = $fallback['domain'] ?? [];

                    return array_values(array_filter(array_map('strval', \is_array($domains) ? $domains : [$domains]), static fn (string $domain): bool => '' !== $domain));
                }
            }
        }

        return [];
    }
}
