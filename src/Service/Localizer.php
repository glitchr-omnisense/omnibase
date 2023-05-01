<?php

namespace Base\Service;

use Base\Cache\Abstract\AbstractLocalCache;
use DateTimeZone;
use Exception;
use Locale;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Intl\Locales;
use Symfony\Component\Intl\Timezones;

class Localizer extends AbstractLocalCache implements LocalizerInterface
{
    public const LOCALE_FORMAT = "xx-XX";

    protected ?ParameterBagInterface $parameterBag = null;

    protected static $isLate = null; // Turns on when on kernel request

    public static function isLate(): bool
    {
        return is_string(self::$isLate) || (self::$isLate ?? false);
    }

    public static function markAsLate(?string $location = null)
    {
        $backtrace = debug_backtrace()[1] ?? null;
        $location = ($backtrace ? $backtrace['class'] . "::" . $backtrace['function'] : true);
        self::$isLate = $location;
    }

    protected bool $localeHasChanged = false;

    public function localeHasChanged()
    {
        return $this->localeHasChanged;
    }

    public function markAsChanged()
    {
        $this->localeHasChanged = true;
        return $this;
    }

    public function __construct(ParameterBagInterface $parameterBag, TranslatorInterface $translator, string $cacheDir)
    {
        $this->parameterBag = $parameterBag;
        $this->translator = $translator;

        parent::__construct($cacheDir);
    }

    protected static ?string $defaultLocale = null;
    protected static ?array $fallbackLocales = null;

    public function warmUp(string $cacheDir): bool
    {
        self::$locales = $this->getCache("/Localize/Locales", self::getLocales());
        self::$fallbackLocales = self::$fallbackLocales ?? self::normalizeLocale($this->translator->getFallbackLocales());
        self::$defaultLocale = self::$defaultLocale ?? self::normalizeLocale($this->parameterBag->get("kernel.default_locale"));

        return true;
    }

    /**
     * @var TranslatorInterface
     */
    protected ?TranslatorInterface $translator = null;

    public const SEPARATOR = "-";

    public static function __toLocale(string $locale, ?string $separator = self::SEPARATOR): string
    {
        $lang = self::__toLocaleLang($locale);
        $country = self::__toLocaleCountry($locale);

        return $lang . $separator . $country;
    }

    public static function __toLocaleLang(string $locale): string
    {
        $lang = $locale ? substr($locale, 0, 2) : null;
        if ($lang === null || !array_key_exists($lang, self::getLocales())) {
            $lang = substr(self::getDefaultLocale(), 0, 2);
        }

        return $lang;
    }

    public static function __toLocaleCountry(string $locale): string
    {
        $defaultCountry = self::getDefaultLocale() ? substr(self::getDefaultLocale(), 3, 2) : null;
        $availableCountries = array_transforms(fn($k, $l): array => $l !== null ? [substr($l, 0, 2), [substr($l, 3, 2)]] : null, self::getAvailableLocales());

        $lang = self::__toLocaleLang($locale);
        $langCountries = $availableCountries[$lang] ?? self::getLocales()[$lang] ?? [];

        $country = substr($locale, 3, 2);
        $country = $country ?: null;
        return $country !== null && in_array($country, $langCountries) ? $country : ($langCountries[0] ?? $defaultCountry);
    }

    private static ?array $locales = null;

    public static function getLocales()
    {
        if (self::$locales === null) {
            self::$locales = [];
            foreach (Locales::getLocales() as $locale) {
                // NB: Only keep xx-YY locale format
                if (!preg_match('/[a-z]{2}.[A-Z]{2}/', $locale)) {
                    continue;
                }

                $lang = substr($locale, 0, 2);
                $country = substr($locale, 3, 2);

                if (!array_key_exists($lang, self::$locales)) {
                    self::$locales[$lang] = [];
                }
                self::$locales[$lang][] = $country;
            }
        }

        return self::$locales;
    }

    public function compatibleLocale(string $locale, string $preferredLocale, ?array $availableLocales = null): ?string
    {
        if (in_array($locale, $availableLocales)) {
            return false;
        }
        if (in_array($locale, $availableLocales) && $locale == $preferredLocale) {
            return true;
        }

        if (in_array($preferredLocale, $availableLocales ?? $this->getAvailableLocales()) &&
            $this->__toLocaleLang($locale) == $this->__toLocaleLang($preferredLocale)) {
            $availableLangs = array_map(fn($l) => $this->__toLocaleLang($l), $availableLocales ?? $this->getAvailableLocales());
            $defaultLangKey = array_search($this->__toLocaleLang($preferredLocale), $availableLangs);
            $defaultLocaleKey = array_search($preferredLocale, $availableLocales);

            return $defaultLangKey == $defaultLocaleKey;
        }

        return false;
    }

    public function getLocale(?string $locale = null): string
    {
        return self::normalizeLocale($locale ?? $this->translator->getLocale());
    }

    public function setLocale(string $locale, ?Request $request = null)
    {
        $currentLocale = $this->getLocale();
        if ($request !== null) {
            if (self::isLate()) {
                $method = __CLASS__ . "::" . __FUNCTION__;
                $location = is_string(self::$isLate) ? self::$isLate : "LocaleSubscriber::onKernelRequest";
                throw new Exception("You cannot call " . $method . ", after \"" . $location . "\" got triggered.");
            }

            // Symfony request needs underscore separator, regardless of the constant defined above
            $request->setLocale(substr_replace($locale, "_", 2, 1));
        }

        $this->translator->setLocale(substr_replace($locale, "_", 2, 1));

        $this->localeHasChanged = $currentLocale !== $locale;
        return $this;
    }

    public function setDefaultLocale(?string $defaultLocale)
    {
        self::$defaultLocale = $defaultLocale;
    }

    public static function getDefaultLocale(): ?string
    {
        return self::$defaultLocale;
    }

    public static function getFallbackLocales(): array
    {
        return self::$fallbackLocales;
    }

    public static function getAvailableLocales(): array
    {
        return array_filter(array_unique(array_merge([self::$defaultLocale], self::$fallbackLocales ?? [])));
    }

    public static function getDefaultLocaleLang(): ?string
    {
        return self::$defaultLocale ? substr(self::$defaultLocale, 0, 2) : null;
    }

    public static function getFallbackLocaleLangs(): array
    {
        return array_map(fn($l) => self::__toLocaleLang($l), self::$fallbackLocales);
    }

    public static function getAvailableLocaleLangs(): array
    {
        return array_unique(array_merge([self::getDefaultLocaleLang()], self::getFallbackLocaleLangs() ?? []));
    }

    public static function getDefaultLocaleCountry(): ?string
    {
        return self::$defaultLocale ? substr(self::$defaultLocale, 3, 2) : null;
    }

    public static function getFallbackLocaleCountries(): array
    {
        return array_map(fn($l) => self::__toLocaleCountry($l), self::$fallbackLocales);
    }

    public static function getAvailableLocaleCountries(): array
    {
        return array_unique(array_merge([self::getDefaultLocaleCountry()], self::getFallbackLocaleCountries() ?? []));
    }

    public function getLocaleLangName(?string $locale = null): ?string
    {
        return Languages::getName($this->getLocaleLang($locale));
    }

    public function getLocaleLang(?string $locale = null): string
    {
        if ($locale === null) {
            $locale = $this->getLocale();
        }
        return self::__toLocaleLang($locale);
    }

    public function getLocaleCountryName(?string $locale = null): string
    {
        return Countries::getName($this->getLocaleCountry($locale));
    }

    public function getLocaleCountry(?string $locale = null): string
    {
        if ($locale === null) {
            $locale = $this->getLocale();
        }
        return self::__toLocaleCountry($locale);
    }

    protected static array $cacheLocales = [];

    public static function normalizeLocale(string|array $locale, string $separator = self::SEPARATOR): string|array
    {
        //
        // Shape array elements
        if (is_array($locale)) {
            $locales = [];
            foreach ($locale as $l) {
                $locales[] = self::normalizeLocale($l, $separator);
            }

            return $locales;
        }

        if (array_key_exists($locale, self::$cacheLocales)) {
            return self::$cacheLocales[$locale];
        }

        //
        // Correct length, perhaps wrong separator.. just normalize
        if (strlen($locale) == 5) {
            self::$cacheLocales[$locale] = substr($locale, 0, 2) . self::SEPARATOR . substr($locale, 3, 2);
            return self::$cacheLocales[$locale];
        }

        //
        // Missing information.. try to guess..
        $lang = $locale ? substr($locale, 0, 2) : null;
        if ($lang === null || !array_key_exists($lang, self::getLocales())) {
            $lang = substr(self::getDefaultLocale(), 0, 2);
        }

        $defaultCountry = self::getDefaultLocale() ? substr(self::getDefaultLocale(), 3, 2) : ($lang == "en" ? "GB" : first(self::$locales[$lang]) ?? null);
        $availableCountries = array_transforms(fn($k, $l): array => $l !== null ? [substr($l, 0, 2), [substr($l, 3, 2)]] : null, self::getAvailableLocales());
        $langCountries = $availableCountries[$lang] ?? [];

        $country = substr($locale, 3, 2);
        $country = $country ?: null;
        $country = $country !== null && in_array($country, $langCountries) ? $country : ($langCountries[0] ?? $defaultCountry);

        self::$cacheLocales[$locale] = $lang . self::SEPARATOR . $country;
        return self::$cacheLocales[$locale];
    }


    /**
     * @var string
     */
    protected string $timezone;

    public function getTimezone(): string
    {
        return $this->timezone ?? self::getDefaultTimezone();
    }

    public function setTimezone(string $timezone): self
    {
        $this->timezone = in_array($timezone, timezone_identifiers_list()) ? $timezone : $this->getDefaultTimezone();
        return $this;
    }

    public static function getDefaultTimezone(): string
    {
        return "UTC";
    }

    public static function getAvailableTimezones(): array
    {
        return timezone_identifiers_list();
    }

    public static function __toGMT(string $timezone): string
    {
        return Timezones::getGmtOffset($timezone);
    }

    public static function __toTimezone(string $countryCode)
    {
        $alpha2country = Countries::getAlpha2Code($countryCode);
        return DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $alpha2country);
    }

    /**
     * @var string
     */
    protected string $country;

    public function getCountry(): string
    {
        return $this->country ?? $this->getLocaleCountry();
    }

    public function getCountryName(?string $displayLocale = null)
    {
        return Locale::getDisplayRegion($this->countryCode, $displayLocale);
    }

    public function setCountry(string $countryCode): self
    {
        $this->country = in_array($countryCode, Countries::getCountryCodes()) ? $countryCode : $this->getLocaleCountry();
        return $this;
    }

    /**
     * @var string
     */
    protected string $currency;

    public static function __toSymbol(string $currency)
    {
        return Currencies::getSymbol($currency);
    }

    public function getCurrency(): string
    {
        return $this->currency ?? $this->getLocaleCountry();
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = Currencies::exists($currency) ? $currency : "USD";
        return $this;
    }
}
