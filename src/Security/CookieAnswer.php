<?php

namespace Base\Security;

/**
 * The visitor's answer about cookies, read on the server: the page's script
 * (@glitchr/flowjs/cookie, which omnibase/consent configures) keeps it in the
 * cookie "cookie-consent" - {"c": {"USER": 1, "LOCATION": 0}, ...}, one yes
 * or no per category. For the few cookies the server writes itself: the
 * script lets nothing be written under a refused category, and neither does
 * the server.
 */
final class CookieAnswer
{
    /** The library's default name; a site that renames it (consent.options.record.name) has no answer here. */
    public const COOKIE = 'cookie-consent';

    /**
     * true or false, null when the visitor has not answered for this
     * category - or the answer is not in this cookie.
     *
     * @param array<string, mixed>|null $cookies the request's; PHP's own by default
     */
    public static function of(string $category, ?array $cookies = null): ?bool
    {
        $answer = json_decode((string) (($cookies ?? $_COOKIE)[self::COOKIE] ?? ''), true);
        $categories = \is_array($answer) && \is_array($answer['c'] ?? null) ? $answer['c'] : [];
        $category = strtoupper(trim($category));

        return \array_key_exists($category, $categories) ? (bool) $categories[$category] : null;
    }

    /** @param array<string, mixed>|null $cookies */
    public static function refused(string $category, ?array $cookies = null): bool
    {
        return false === self::of($category, $cookies);
    }
}
