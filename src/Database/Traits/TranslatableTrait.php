<?php

namespace Base\Database\Traits;

use Base\Database\TranslationInterface;
use Base\Exception\MissingLocaleException;
use Base\Exception\TranslationAmbiguityException;
use Base\Service\BaseService;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Symfony\Component\PropertyAccess\PropertyAccess;

const __TRANSLATION_SUFFIX__ = 'Translation';
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

            self::$translationClass = $class . __TRANSLATION_SUFFIX__;
            while(!class_exists(self::$translationClass) || !is_subclass_of(self::$translationClass, TranslationInterface::class)) {

                if(!get_parent_class($class)) throw new Exception("No translation entity found for ".$class);

                $class = get_parent_class($class);
                self::$translationClass = $class . __TRANSLATION_SUFFIX__;
            }

            return self::$translationClass;
        }

        $translationClass = $class . __TRANSLATION_SUFFIX__;
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
        if ($locale < 0) $locale = BaseService::getLocaleProvider()->getDefaultLocale();

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

    public function __call(string $methodOrProperty, array $arguments)
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        //
        // Proxy setter method for (current & default) locale
        $isSetter = str_starts_with($methodOrProperty, "set");
        if($isSetter) {

            $entityTranslation = $this->translate();
            $property = lcfirst(substr($methodOrProperty, 3));
            if(property_exists($entityTranslation, $property)) {

                if (!$accessor->isWritable($entityTranslation, $property))
                    throw new \BadMethodCallException("Property \"$methodOrProperty\" not writable in ". $this->getTranslationEntityClass());

                $accessor->setValue($entityTranslation, $property, ...$arguments);
                return $this;
            }
        }

        //
        // Proxy getter method for current locale
        $defaultLocale = BaseService::getLocaleProvider()->getDefaultLocale();
        $entityTranslation = $this->translate();

        $value = null;
        if(method_exists($entityTranslation, $methodOrProperty))
            $value = $entityTranslation->{$methodOrProperty}(...$arguments);
        else if(method_exists($entityTranslation, "get".ucfirst($methodOrProperty)))
            $value = $entityTranslation->{"get".ucfirst($methodOrProperty)}(...$arguments);
        else if ($accessor->isReadable($entityTranslation, $methodOrProperty))
            $value = $accessor->getValue($entityTranslation, $methodOrProperty);

        // If current locale is empty.. then try to access value from default locale
        // (unless is was already the default locale)
        if ($value !== null) return $value;

        //
        // Proxy getter method for default locale
        if ($entityTranslation->getLocale() == $defaultLocale) return $value;
        else {

            $entityTranslation = $this->translate($defaultLocale);
            if(method_exists($entityTranslation, $methodOrProperty))
                return $entityTranslation->{$methodOrProperty}(...$arguments);
            else if(method_exists($entityTranslation, "get".ucfirst($methodOrProperty)))
                return $entityTranslation->{"get".ucfirst($methodOrProperty)}(...$arguments);
            else if ($accessor->isReadable($entityTranslation, $methodOrProperty)) 
                return $accessor->getValue($entityTranslation, $methodOrProperty);
        }

        //
        // Parent fallback for magic __call
        if(method_exists(get_parent_class(),"__call")) 
            return parent::__call($methodOrProperty, $arguments);

        //
        // Failed to find a valid accessor
        throw new \BadMethodCallException("Method (or property accessor) \"$methodOrProperty\" not found in ". $this->getTranslationEntityClass());
    }
}
