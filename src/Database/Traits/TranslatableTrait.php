<?php

namespace Base\Database\Traits;

use Base\Database\TranslationInterface;
use Base\Exception\MissingLocaleException;
use Base\Exception\TranslationAmbiguityException;
use Base\Service\BaseService;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;

trait TranslatableTrait
{
    private static $translationClass;
    public static function getTranslationEntityClass(
        bool $withInheritance = true, // Required in some cases where you must access main class without inheritance
        bool $selfClass = false // Proxies\__CG__ error, if not true during discriminator map building (TranslationType)
    ): ?string
    {
        $class = ($selfClass ? self::class : static::class);
        
        $prefix = "Proxies\__CG__\\";
        if (strpos($class, $prefix) === 0) 
            $class = substr($class, strlen($prefix));

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
     * @var TranslationInterface[]|Collection
     */
    protected $translations;

    public function getTranslations()
    {
        if ($this->translations === null)
            $this->translations = new ArrayCollection();

        return $this->translations;
    }

    public function addTranslation(TranslationInterface $translation)
    {
        if($translation !== null) {

            if(!$translation->getLocale())
                throw new MissingLocaleException("Missing locale information.");

            $this->getTranslations()->set($translation->getLocale(), $translation);
            $translation->setTranslatable($this);
        }

        return $this;
    }

    public function removeTranslation(TranslationInterface $translation): void
    {
        $this->getTranslations()->removeElement($translation);
    }

    public function translate(?string $locale = null)
    {
        $locale = $locale ?? BaseService::getLocaleProvider()->getLocale();
        if(!$locale) throw new MissingLocaleException("Missing locale information.");

        $translations = $this->getTranslations();
        $translationClass = self::getTranslationEntityClass(true, false);

        $translation = $translations[$locale] ?? null;
        if(!$translation) {

            $keys = $translations->getKeys();
            $defaultKey = array_search($locale, $keys);
            $firstKey = ( \count($keys) > 1 ) ? $keys[$defaultKey] : $keys[0] ?? null;
            
            $translation = $firstKey ? $translations[$firstKey] : null;
            if(!$translation) {

                $translation = new $translationClass;
                $translation->setLocale($locale);

                $this->addTranslation($translation);
            }
        }

        return $translation;
    }
}
