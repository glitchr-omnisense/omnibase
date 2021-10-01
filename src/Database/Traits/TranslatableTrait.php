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
     * @var TranslationInterface[]|Collection
     */
    protected $translations;

    public function getTranslations()
    {
        if ($this->translations === null)
            $this->translations = new ArrayCollection();

        return $this->translations;
    }

    public function addTranslation($translation)
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
        $translationClass = self::getTranslationEntityClass();
        
        $translation = $translations[$locale] ?? null;
        if(!$translation) {

            if( \count($keys = $translations->getKeys()) > 1 )
                throw new TranslationAmbiguityException("Translation ambiguity exception.");

            $firstKey = $keys[0] ?? null;
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
