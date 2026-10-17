<?php

namespace Base\Service\Model;

use Base\Contract\Locales;
use Base\Contract\Runtime;
use Symfony\Component\Intl\Locales as IntlLocales;

/**
 * omnibase's locale form, xx-XX, without the framework (plan "omnibase
 * agnostique", C3e): the rule Base\Service\Localizer::normalizeLocale() and
 * __toLocaleLang() apply, taken out of the Localizer (a Symfony service, its
 * statics filled at its warm-up), which now asks it with its own default and
 * fallback locales - the same strings, by construction.
 *
 * - "fr_FR", "fr-FR": five characters, the separator made a dash;
 * - "fr", "fr-XX": the language when intl knows it (else the default
 *   locale's), and its country among the available locales of that language
 *   (the first of them; else the default locale's country), keeping the one
 *   given when it is available.
 *
 * here() is what the entities ask (TranslatableTrait, TranslationTrait):
 * the default and available locales of the Locales contract
 * (Runtime::locales(); under Symfony Base\Bridge\Symfony\LocalizerLocales,
 * the Localizer's own), none when the registry has no Locales, as the
 * Localizer's statics are empty before it is built. Not a contract: a rule
 * over one.
 */
final class LocaleNormalizer
{
    public const SEPARATOR = '-';

    /** @var array<string, list<string>>|null */
    private static ?array $locales = null;

    /**
     * Every language of intl's xx-YY locales, and its countries.
     *
     * @return array<string, list<string>>
     */
    public static function getLocales(): array
    {
        if (null === self::$locales) {
            self::$locales = [];
            foreach (IntlLocales::getLocales() as $locale) {
                // NB: Only keep xx-YY locale format
                if (!preg_match('/[a-z]{2}.[A-Z]{2}/', $locale)) {
                    continue;
                }

                self::$locales[substr($locale, 0, 2)][] = substr($locale, 3, 2);
            }
        }

        return self::$locales;
    }

    /**
     * @param list<string>                     $availableLocales the default first, in omnibase's form
     * @param array<string, list<string>>|null $locales          every language and its countries; intl's when null
     */
    public static function normalize(string $locale, ?string $defaultLocale, array $availableLocales, ?array $locales = null): string
    {
        // Correct length, perhaps wrong separator.. just normalize
        if (5 == strlen($locale)) {
            return substr($locale, 0, 2).self::SEPARATOR.substr($locale, 3, 2);
        }

        // Missing information.. try to guess..
        $locales ??= self::getLocales();
        $lang = self::lang($locale, $defaultLocale, $locales);

        $defaultCountry = $defaultLocale ? substr($defaultLocale, 3, 2) : ('en' == $lang ? 'GB' : first($locales[$lang] ?? null) ?? null);
        $availableCountries = array_transforms(fn ($k, $l): array => null !== $l ? [substr($l, 0, 2), [substr($l, 3, 2)]] : null, $availableLocales);
        $langCountries = $availableCountries[$lang] ?? [];

        $country = substr($locale, 3, 2);
        $country = $country ?: null;
        $country = null !== $country && in_array($country, $langCountries) ? $country : ($langCountries[0] ?? $defaultCountry);

        return $lang.self::SEPARATOR.$country;
    }

    /**
     * The language of a locale: its first two letters when intl knows them, else the default locale's.
     *
     * @param array<string, list<string>>|null $locales every language and its countries; intl's when null
     */
    public static function lang(string $locale, ?string $defaultLocale, ?array $locales = null): string
    {
        $locales ??= self::getLocales();
        $lang = $locale ? substr($locale, 0, 2) : null;
        if (null === $lang || !array_key_exists($lang, $locales)) {
            $lang = substr((string) $defaultLocale, 0, 2);
        }

        return $lang;
    }

    /**
     * normalize() with the Locales contract's default and available locales (Runtime), as the
     * entities ask; none when the registry has no Locales.
     */
    public static function here(string $locale): string
    {
        if (5 == strlen($locale)) {
            return self::normalize($locale, null, []);
        }

        $locales = Runtime::find(Locales::class);

        return self::normalize($locale, $locales?->getDefaultLocale(), $locales?->getAvailableLocales() ?? []);
    }
}
