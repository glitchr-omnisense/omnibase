<?php

namespace Base\Database\Traits;

use Base\Database\TranslationInterface;
use Exception;

trait TranslatableTrait
{
    private static $translationClass;
    public static function getTranslationEntityClass(bool $withInheritance = true): ?string
    {
        $class = static::class;
        if($withInheritance) {

            self::$translationClass = $class . 'Translation';
            while(!class_exists(self::$translationClass) || !is_subclass_of(self::$translationClass, TranslationInterface::class)) {

                if(!get_parent_class($class)) throw new Exception("No translation entity class found.");

                $class = get_parent_class($class);
                self::$translationClass = $class . 'Translation';
            }

            return self::$translationClass;
        }

        $translationClass = $class . 'Translation';
        if(!class_exists($translationClass) || !is_subclass_of($translationClass, TranslationInterface::class))
            return null;

        return $translationClass;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $translatable_id;

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
