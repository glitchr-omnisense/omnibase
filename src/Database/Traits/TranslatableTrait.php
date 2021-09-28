<?php

namespace Base\Database\Traits;

use Base\Database\TranslationInterface;
use Exception;

trait TranslatableTrait
{
    public static function getTranslationEntityClass(): string
    {
        return get_class() . 'Translation';
    }

    /**
     * @var TranslationInterface[]|Collection
     */
    protected $translations;

    public function addTranslation(TranslationInterface $translation): void
    {
        $this->getTranslations()->set((string) $translation->getLocale(), $translation);
        $translation->setTranslatable($this);
    }

    public function removeTranslation(TranslationInterface $translation): void
    {
        $this->getTranslations()->removeElement($translation);
    }

    public function translate(?string $locale = null)
    {
        if( $locale = $this->getLocale() ) {

            $translationEntityClass = self::getTranslationEntityClass();
            return $translations[$locale] ?? new $translationEntityClass;    
        }
        
        throw new Exception("Unknown locale provided in \"".get_class()."\"");
    }

    /**
     * @var string
     */
    protected string $locale = "";
    public function getLocale(): string
    {
        $locale = $this->locale;
        if( in_array($locale, $this->fallbackLocales) ) return $locale;

        $locale = explode('-', $locale)[0];
        if( in_array($locale, $this->fallbackLocales) ) return $locale;

        return $this->defaultLocale;
    }
    public function setLocale(string $locale)
    {
        $this->locale = $locale;
        return $this;
    }
    
    /**
     * @var string
     */
    protected string $defaultLocale = "";

    public function getDefaultLocale(): string { return $this->defaultLocale; }
    public function setDefaultLocale(string $defaultLocale)
    {
        $this->defaultLocale = $defaultLocale;
        return $this;
    }

    /**
     * @var array
     */
    protected array $fallbackLocales = [];

    public function getFallbackLocales(): array { return $this->fallbackLocale ?? []; }
    public function setFallbackLocales(array $fallbackLocales)
    {
        $this->fallbackLocales = $fallbackLocales;
        return $this;
    }
}