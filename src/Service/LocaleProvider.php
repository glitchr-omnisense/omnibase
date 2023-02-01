<?php

namespace Base\Service;

use Base\Cache\Abstract\AbstractLocalCache;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Intl\Locales;

class LocaleProvider extends AbstractLocalCache implements LocaleProviderInterface
{
    public const UNIVERSAL = "xx_XX";

    protected $requestStack = null;
    protected $parameterBag = null;

    public function __construct(ParameterBagInterface $parameterBag, TranslatorInterface $translator, string $cacheDir)
    {
        $this->parameterBag = $parameterBag;
        $this->translator   = $translator;

        parent::__construct($cacheDir);
    }

    protected static ?string $defaultLocale   = null;
    protected static ?array $fallbackLocales  = null;

    public function warmUp(string $cacheDir): bool
    {
        self::$locales          = $this->getCache("/Locales", self::getLocales());
        self::$fallbackLocales  = self::$fallbackLocales ?? self::normalize($this->translator->getFallbackLocales());
        self::$defaultLocale    = self::$defaultLocale   ?? self::normalize($this->parameterBag->get("kernel.default_locale"));
        
        return true;
    }

    /**
     * @var TranslatorInterface
     */
    protected $translator = null;

    public const SEPARATOR = "-";
    public static function __toLocale (string $locale, ?string $separator = self::SEPARATOR): string
    {
        $lang    = self::__toLang($locale);
        $country = self::__toCountry($locale);

        return $lang.$separator.$country;
    }

    public static function __toLang (string $locale): string
    {
        $lang = $locale ? substr($locale,0,2) : null;
        if ($lang === null || !array_key_exists($lang, self::getLocales()))
            $lang = substr(self::getDefaultLocale(),0,2);

        return $lang;
    }

    public static function __toCountry (string $locale): string
    {
        $defaultCountry     = self::getDefaultLocale() ? substr(self::getDefaultLocale(),3,2) : null;
        $availableCountries = array_transforms(fn($k, $l):array => $l !== null ? [substr($l,0,2), [substr($l,3,2)]] : null, self::getAvailableLocales());

        $lang           = self::__toLang($locale);
        $langCountries  = $availableCountries[$lang] ?? self::getLocales()[$lang] ?? [];

        $country = substr($locale,3,2);
        $country = $country ? $country : null;
        $country = $country !== null && in_array($country, $langCountries) ? $country : ($langCountries[0] ?? $defaultCountry);

        return $country;
    }

    private static ?array $locales = null;
    public static function getLocales()
    {
        if(self::$locales === null) {

            self::$locales = [];
            foreach(Locales::getLocales() as $locale) {

                // NB: Only keep xx-YY locale format
                if(!preg_match('/[a-z]{2}.[A-Z]{2}/', $locale)) continue;

                $lang    = substr($locale,0,2);
                $country = substr($locale,3,2);

                if(!array_key_exists($lang, self::$locales)) self::$locales[$lang] = [];
                self::$locales[$lang][] = $country;
            }
        }

        return self::$locales;
    }

    public function compatibleLocale(string $locale, string $preferredLocale, ?array $availableLocales = null): ?string
    {
        if(in_array($locale, $availableLocales)) return false;
        if(in_array($locale, $availableLocales) && $locale == $preferredLocale) return true;

        if(in_array($preferredLocale, $availableLocales ?? $this->getAvailableLocales()) &&
           $this->__toLang($locale) == $this->__toLang($preferredLocale)) {

            $availableLangs = array_map(fn($l) => $this->__toLang($l), $availableLocales ?? $this->getAvailableLocales());
            $defaultLangKey = array_search($this->__toLang($preferredLocale), $availableLangs);
            $defaultLocaleKey = array_search($preferredLocale, $availableLocales);

            return $defaultLangKey == $defaultLocaleKey;
        }

        return false;
    }

    protected static $isLate = null; // Turns on when on kernel request
    public static function isLate(): bool { return is_string(self::$isLate) ? true : self::$isLate ?? false; }
    public static function markAsLate(?string $location = null)
    {
        $backtrace = debug_backtrace()[1] ?? null;
        $location = ($backtrace ? $backtrace['class']."::".$backtrace['function'] : true);
        self::$isLate = $location;
    }

    protected static $i = 0;
    public function getLocale(?string $locale = null): string {

        return self::normalize($locale ?? $this->translator->getLocale());
    }
    
    public function setLocale(string $locale, ?Request $request = null)
    {
        $currentLocale = $this->getLocale();
        if($request !== null) {

            if(self::isLate()) {

                $method = __CLASS__."::".__FUNCTION__;
                $location = is_string(self::$isLate) ? self::$isLate : "LocaleSubscriber::onKernelRequest";
                throw new \Exception("You cannot call ".$method.", after \"".$location."\" got triggered.");
            }

            // Symfony request needs underscore separator, regardless of the constant defined above
            $request->setLocale(substr_replace($locale, "_", 2, 1));
        }

        $this->translator->setLocale(substr_replace($locale, "_", 2, 1));

        $this->hasChanged = $currentLocale !== $locale;
        return $this;
    }

    protected bool $hasChanged = false;
    public function hasChanged() { return $this->hasChanged; }
    public function markAsChanged()
    {
        $this->hasChanged = true;
        return $this;
    }

    public function setDefaultLocale(?string $defaultLocale) { self::$defaultLocale = $defaultLocale; }
    public static function getDefaultLocale(): ?string { return self::$defaultLocale; }
    public static function getFallbackLocales(): array { return self::$fallbackLocales; }
    public static function getAvailableLocales(): array
    {
        return array_filter(array_unique(array_merge([self::$defaultLocale], self::$fallbackLocales ?? [])));
    }
    public static function getDefaultLang(): ?string { return self::$defaultLocale ? substr(self::$defaultLocale,0,2) : null; }
    public static function getFallbackLangs(): array { return array_map(fn($l) => self::__toLang($l), self::$fallbackLocales); }
    public static function getAvailableLangs(): array
    {
        return array_unique(array_merge([self::getDefaultLang()], self::getFallbackLangs() ?? []));
    }
    public static function getDefaultCountry(): ?string  { return self::$defaultLocale ? substr(self::$defaultLocale,3,2) : null; }
    public static function getFallbackCountries(): array { return array_map(fn($l) => self::__toCountry($l), self::$fallbackLocales); }
    public static function getAvailableCountries(): array
    {
        return array_unique(array_merge([self::getDefaultCountry()], self::getFallbackCountries() ?? []));
    }

    public function getLangName(?string $locale = null): ?string { return Languages::getName($this->getLang($locale)); }
    public function getLang(?string $locale = null): string
    {
        if($locale === null) $locale = $this->getLocale();
        return self::__toLang($locale);
    }

    public function getCountryName(?string $locale = null): string { return Countries::getName($this->getCountry($locale)); }
    public function getCountry(?string $locale = null): string
    {
        if($locale === null) $locale = $this->getLocale();
        return self::__toCountry($locale);
    }

    protected static $cacheLocales = [];
    public static function normalize(string|array $locale, string $separator = self::SEPARATOR): string|array
    {
        //
        // Shape array elements
        if(is_array($locale)) {

            $locales = [];
            foreach($locale as $l)
                $locales[] = self::normalize($l, $separator);

            return $locales;
        }

        if(array_key_exists($locale, self::$cacheLocales))
            return self::$cacheLocales[$locale];

        //
        // Correct length, perhaps wrong separator.. just normalize
        if(strlen($locale) == 5) {
            
            self::$cacheLocales[$locale] = substr($locale,0,2) . self::SEPARATOR . substr($locale,3,2);
            return self::$cacheLocales[$locale];
        }

        //
        // Missing information.. try to guess..
        $lang = $locale ? substr($locale,0,2) : null;
        if ($lang === null || !array_key_exists($lang, self::getLocales()))
            $lang = substr(self::getDefaultLocale(),0,2);

        $defaultCountry     = self::getDefaultLocale() ? substr(self::getDefaultLocale(),3,2) : ($lang == "en" ? "GB" : first(self::$locales[$lang]) ?? null);
        $availableCountries = array_transforms(fn($k, $l):array => $l !== null ? [substr($l,0,2), [substr($l,3,2)]] : null, self::getAvailableLocales());
        $langCountries  = $availableCountries[$lang] ?? [];

        $country = substr($locale,3,2);
        $country = $country ? $country : null;
        $country = $country !== null && in_array($country, $langCountries) ? $country : ($langCountries[0] ?? $defaultCountry);

        self::$cacheLocales[$locale] = $lang.self::SEPARATOR.$country;
        return self::$cacheLocales[$locale];
    }
}
