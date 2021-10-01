<?php

namespace Base\Service;

use App\Entity\User;
use Base\Exception\MissingLocaleException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Locale;
use Symfony\Component\Intl\Locales;
use Symfony\Contracts\Translation\TranslatorInterface;

class LocaleProvider implements LocaleProviderInterface
{
    public const SEPARATOR = "_";

    /**
     * @var RequestStack
     */
    protected $requestStack = null;

    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag = null;

    /**
     * @var TranslatorInterface
     */
    protected $translator = null;
    
    protected static ?string $defaultLocale   = null;    
    protected static ?array $fallbackLocales  = null;
    protected static ?array $availableLocales = null;

    public function __construct(RequestStack $requestStack, ParameterBagInterface $parameterBag, ?TranslatorInterface $translator)
    {
        $this->requestStack = $requestStack;
        $this->parameterBag = $parameterBag;
        $this->translator   = $translator;

        if(! $parameterBag->has("kernel.default_locale")) 
            throw new MissingLocaleException("Missing default locale.");
            
        self::$defaultLocale    = self::$defaultLocale   ?? self::normalize($parameterBag->get("kernel.default_locale"));
        self::$fallbackLocales  = self::$fallbackLocales ?? self::normalizeArray($this->translator->getFallbackLocales());
    }

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
                self::$locales[$lang][] = $lang . self::SEPARATOR . $country;
            }
        }

        return self::$locales;
    }
    
    public static function normalize(?string $locale): ?string { return self::getLang($locale) . self::SEPARATOR . self::getCountry($locale); }
    public static function normalizeArray(?array $locales): ?array { return array_map(fn ($l) => self::normalize($l), $locales); }

    public function getLocale(?string $locale = null): ?string
    {
        if($locale === null) {

            $currentRequest = $this->requestStack->getCurrentRequest();
            if ($userLocale = User::getCookie("locale"))
                $locale = $userLocale;

            else if (! $currentRequest instanceof Request)
                $locale = $this->getDefaultLocale();

            else if ( ($currentLocale = $currentRequest->getLocale()) )
                $locale = $currentLocale;

            else if ($this->translator !== null)
                $locale = $this->translator->getLocale();
        }
        
        if(!$locale)
            throw new MissingLocaleException("Missing locale.");

        return self::normalize($locale);
    }

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
        $lang = self::getLang($locale);
        $langCountries = self::getLocales()[$lang];
        $locale = in_array($locale, $langCountries) ? $locale : ($langCountries[0] ?? self::getDefaultLocale());

        return substr($locale,3,2);
    }
}