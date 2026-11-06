<?php

namespace Base\Request\Rule;

use Base\Request\Decision;

/**
 * The language of a page (plan "omnibase agnostic", step F5e, slice 2; formerly omnibridge/symfony's
 * LocalizerSubscriber and omnibridge/laravel's Http\Localize, each its copy), and the one the next page
 * remembers.
 *
 * The order: the page's address (a route of one language, "/en/..." or "/datenschutz"); then, for a member
 * signed in, their own language - which their choice of language updates - before the one their last page
 * was shown in (the LANG cookie: a first visit before signing in, in the browser's language, set it and kept
 * a member whose language is French in English); while impersonating, the impersonator's choice (LANG),
 * which does not change the member's; then the browser's (Accept-Language), on a first visit; then the
 * site's. The browser says nothing when it sends no Accept-Language, or Symfony's Request::create() default
 * (a test client's, a sub-request's). A language counts when the site speaks it; a route's own is the site's
 * by declaration.
 *
 * The page's language is remembered in LANG for the next one (the site's locale, en-GB), unless the page set
 * it itself (a change of language).
 */
final class Locale
{
    public const COOKIE = 'LANG';

    /** What Symfony's Request::create() says when it is told no language. */
    public const REQUEST_DEFAULT_LANGUAGE = 'en-us,en;q=0.5';

    /**
     * @param list<string>              $languages the languages the site speaks (fr, en), its own first: Accept-Language's ties go to it
     * @param \Closure(string): string  $normalize a locale written as the site writes it ("fr" is "fr-FR")
     */
    public function __construct(public readonly array $languages, private readonly \Closure $normalize)
    {
    }

    public static function choose(?string $address, ?string $remembered, ?string $member, bool $impersonating, ?string $browser, string $site): string
    {
        return $address
            ?? ($impersonating ? ($remembered ?? $member) : ($member ?? $remembered))
            ?? $browser
            ?? $site;
    }

    /** A locale of this site's ("de", "fr_FR", "fr-FR"), normalized; null for any other. */
    public function available(?string $locale, bool $declared = false): ?string
    {
        if (!\is_string($locale) || !preg_match('/^[a-zA-Z]{2}([-_][a-zA-Z]{2})?$/', $locale)) {
            return null;
        }
        if (!$declared && !\in_array(strtolower(substr($locale, 0, 2)), $this->languages, true)) {
            return null;
        }

        return ($this->normalize)(strtolower(substr($locale, 0, 2)).substr($locale, 2));
    }

    /**
     * The browser's language among the site's, null when it says nothing.
     *
     * @param \Closure(list<string>): ?string $preferred the host's reading of Accept-Language among languages
     */
    public function browser(?string $acceptLanguage, \Closure $preferred): ?string
    {
        if (null === $acceptLanguage || self::REQUEST_DEFAULT_LANGUAGE === $acceptLanguage) {
            return null;
        }

        return $this->available($preferred($this->languages));
    }

    /** The page's locale, normalized. */
    public function decide(?string $address, ?string $cookie, ?string $member, bool $impersonating, ?string $browser, string $site): string
    {
        return ($this->normalize)(self::choose($this->available($address, true), $this->available($cookie), $this->available($member), $impersonating, $browser, $site));
    }

    /** The answer's: LANG written with the page's locale, unless it holds it already or the page wrote it. */
    public static function remember(string $displayed, ?string $cookie, bool $setByPage): Decision
    {
        return $cookie === $displayed || $setByPage ? Decision::continue() : Decision::continue()->withCookie(self::COOKIE, $displayed);
    }

    /**
     * The visitor's time zone, from the cookie the site's script writes: UTC without one; an invalid one said,
     * and UTC.
     *
     * @return array{0: string, 1: Decision}
     */
    public static function timezone(?string $cookie): array
    {
        $timezone = $cookie ?? 'UTC';
        if (!\in_array($timezone, timezone_identifiers_list(), true)) {
            return ['UTC', Decision::continue()->withNotice('@localizer.timezone.invalid', [$timezone], 'info')];
        }

        return [$timezone, Decision::continue()];
    }
}
