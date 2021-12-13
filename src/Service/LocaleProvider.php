<?php

namespace Base\Service;

use App\Entity\User;
use Base\Exception\MissingLocaleException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Locales;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocaleProvider implements LocaleProviderInterface
{    
    protected $requestStack = null;
    protected $parameterBag = null;
    protected $translator = null;

    public const SEPARATOR = "-";

    private static ?array $locales = null;
    public static function getLocales() 
    { 
        if(!self::$locales) {

            self::$locales = [];
            foreach(Locales::getLocales() as $locale) {

                if(!preg_match('/[a-z]{2}.[A-Z]{2}/', $locale)) continue;
                
                $lang    = substr($locale,0,2);
                $country = substr($locale,3,2);

                if(!array_key_exists($lang, self::$locales)) self::$locales[$lang] = [];
                self::$locales[$lang][] = $country;
            }
        }

        return self::$locales;
    }

    public function __construct(RequestStack $requestStack, ParameterBagInterface $parameterBag, TranslatorInterface $translator)
    {
        $this->requestStack = $requestStack;
        $this->parameterBag = $parameterBag;
        $this->translator   = $translator;

        self::$defaultLocale    = self::$defaultLocale   ?? self::normalize($parameterBag->get("kernel.default_locale"));
        self::$fallbackLocales  = self::$fallbackLocales ?? self::normalizeArray($this->translator->getFallbackLocales());
    }

    protected static $isLate = null; // Turns on when on kernel request
    public static function isLate(): bool { return is_string(self::$isLate) ? true : self::$isLate ?? false; }
    public static function markAsLate(?string $location = null) 
    {
        $backtrace = debug_backtrace()[1] ?? null;
        $location = ($backtrace ? $backtrace['class']."::".$backtrace['function'] : true);
        self::$isLate = $location;
    }

    public function getLocale(?string $locale = null): string { return self::normalize($locale ?? $this->translator->getLocale()); }
    public function setLocale(string $locale, ?Request $request = null)
    {
        if($request !== null) { 

            if(self::isLate()) {
            
                $method = __CLASS__."::".__FUNCTION__;
                $location = is_string(self::$isLate) ? self::$isLate : "LocaleSubscriber::onKernelRequest";
                throw new \Exception("You cannot call ".$method.", after \"".$location."\" got triggered.");
            }
        
            $request->setLocale(substr_replace($locale, "_", 2, 1));
        }

        $this->translator->setLocale($locale);
        return $this;
    }

    protected static ?string $defaultLocale   = null;
    protected static ?array $fallbackLocales  = null;
    protected static ?array $availableLocales = null;

    public function setDefaultLocale(?string $defaultLocale) { self::$defaultLocale = $defaultLocale; }
    public static function getDefaultLocale(): ?string { return self::$defaultLocale; }
    public static function getFallbackLocales(): array { return self::$fallbackLocales; }
    public static function getAvailableLocales(): array 
    {
        return array_unique(array_merge([self::$defaultLocale], self::$fallbackLocales));
    }
    
    public static function getLang(string $locale): string
    {
        $lang = substr($locale,0,2);
        if(!array_key_exists($lang, self::getLocales()))
            $lang = substr(self::getDefaultLocale(),0,2);

        return $lang;
    }

    public static function getCountry(string $locale): string
    {
        $lang           = self::getLang($locale);
        $langCountries  = self::getLocales()[$lang];

        $defaultCountry = substr(self::getDefaultLocale(),3,2);
        
        $country = substr($locale,3,2);
        $country = in_array($country, $langCountries) ? $country : ($langCountries[0] ?? $defaultCountry);        

        return $country;
    }

    public static function normalize(?string $locale): string { return self::getLang($locale) . self::SEPARATOR . self::getCountry($locale); }
    public static function normalizeArray(?array $locales): ?array { return array_map(fn ($l) => self::normalize($l), $locales); }
}