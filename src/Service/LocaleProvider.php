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
    
    protected string $defaultLocale;
    protected array $fallbackLocales = [];

    public function __construct(RequestStack $requestStack, ParameterBagInterface $parameterBag, ?TranslatorInterface $translator)
    {
        $this->requestStack = $requestStack;
        $this->parameterBag = $parameterBag;
        $this->translator   = $translator;

        if(! $parameterBag->has("kernel.default_locale")) 
            throw new MissingLocaleException("Missing default locale.");
        
        $this->defaultLocale    = $this->normalize($parameterBag->get("kernel.default_locale"));
        $this->fallbackLocales  = $this->normalizeArray($this->translator->getFallbackLocales());
        $this->availableLocales = array_unique(array_merge([$this->defaultLocale], $this->fallbackLocales));
    }

    protected static array $locales = [];
    public static function getLocales() 
    { 
        if(empty(self::$locales)) {

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
    
    public function normalize(?string $locale): ?string { return $this->getLocale($locale); }
    public function normalizeArray(?array $locales): ?array
    {
        foreach($locales as $key => $locale)
            $locales[$key] = $this->getLocale($locale);
        
        return $locales;
    }

    public function getDefaultLocale(): ?string
    {
        return $this->defaultLocale;
    }
    
    public function getFallbackLocales(): array
    {
        return $this->fallbackLocales;
    }

    public function getAvailableLocales(): array
    {
        return $this->availableLocales;
    }

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

        return $this->getLang($locale) . self::SEPARATOR . $this->getCountry($locale);
    }

    public function getLang(string $locale): string
    {
        $lang = substr($locale,0,2);
        if(!array_key_exists($lang, $this->getLocales()))
            $lang = substr($this->getDefaultLocale(),0,2);

        return $lang;
    }

    public function getCountry(string $locale): string
    {
        $lang = $this->getLang($locale);
        $langCountries = $this->getLocales()[$lang];
        $locale = in_array($locale, $langCountries) ? $locale : ($langCountries[0] ?? $this->getDefaultLocale());

        return substr($locale,3,2);
    }
}
