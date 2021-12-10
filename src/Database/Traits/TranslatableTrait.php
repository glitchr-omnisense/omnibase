<?php

namespace Base\Database\Traits;

use Base\Database\TranslationInterface;
use Base\Exception\MissingLocaleException;
use Base\Service\BaseService;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use InvalidArgumentException;
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
        $localeProvider = BaseService::getLocaleProvider();
        $defaultLocale = $localeProvider->getDefaultLocale();
        $availableLocales = $localeProvider->getAvailableLocales();
        
        $locale = intval($locale) < 0 ? $defaultLocale : $locale;
        $normLocale = $localeProvider->getLocale($locale); // Locale normalizer

        $translationClass = self::getTranslationEntityClass(true, false);
        $translations = $this->getTranslations();

        $translation = $translations[$normLocale] ?? null;
        /*dump("name: ".$this->name."; translate: ".$normLocale);
        if($this->name == "base.settings.domain" && $normLocale == "fr-FR")
             dump(debug_backtrace());*/
        if(!$translation) {

            // No locale requested, then get the first entry you can find among the available locales
            if($locale === null) {

                // First entry is default locale
                foreach($availableLocales as $availableLocale) {

                    $translation = $translations[$availableLocale] ?? null;
                    if($translation) break;
                }
            }

            // Create a new locale if still not found..
            if(!$translation) {

                $translation = new $translationClass;
                $translation->setLocale($normLocale);

                $this->addTranslation($translation);
            }
        }

        return $translation;
    }

    public function __call(string $method, array $arguments)
    {
        $className   = get_class($this);
        $translationClassName = $this->getTranslationEntityClass();
        $parentClass = get_parent_class();

        //
        // Call magic setter
        if (str_starts_with($method, "set")) {

            $property = lcfirst(substr($method, 3));
            try { return $this->__set($property, $arguments); }
            catch (\BadMethodCallException $e) 
            {
                // Parent fallback setter
                if($parentClass && method_exists($parentClass, "__set")) 
                    return parent::__set($property, $arguments);

            } catch (\InvalidArgumentException $e) { 

                throw $e;
            }
        }

        //
        // Figure out is property exist 
        $property = null;
        if(property_exists($className, $method))
            $property = $method;
        else if(property_exists($translationClassName, $method))
            $property = $method;
        else if(str_starts_with($method, "get") && property_exists($className, lcfirst(substr($method, 3))))
            $property = lcfirst(substr($method, 3));
        else if(str_starts_with($method, "get") && property_exists($translationClassName, lcfirst(substr($method, 3))))
            $property = lcfirst(substr($method, 3));
        else if(str_starts_with($method, "is" ) && property_exists($className, lcfirst(substr($method, 2))))
            $property = lcfirst(substr($method, 2));
        else if(str_starts_with($method, "is" ) && property_exists($translationClassName, lcfirst(substr($method, 2))))
            $property = lcfirst(substr($method, 2));

        //
        // Call magic getter
        if($property) {

            try { return $this->__get($property); }
            catch (\BadMethodCallException $e) 
            {
                // Parent fallback getter
                if($parentClass && method_exists($className, "__get")) 
                    return parent::__get($property);
            }
        }

        //
        // Parent fallback for magic __call
        if($parentClass && method_exists($parentClass,"__call")) 
            return parent::__call($method, $arguments);

        //
        // Failed to find a valid accessor
        throw new \BadMethodCallException("Method \"$method\" not found in class \"".get_class($this)."\" or its corresponding translation class \"".$this->getTranslationEntityClass()."\".");
    }

    public function __set($property, $arguments)
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        //
        // Setter method in called class
        if(property_exists($this, $property)) {

            if (empty($arguments))
                throw new \InvalidArgumentException("Missing argument for setter property \"$property\" in ". get_class($this));
            if (!$accessor->isWritable($this, $property))
                throw new \BadMethodCallException("Property \"$property\" not writable in ". get_class($this));

            $accessor->setValue($this, $property, ...$arguments);
            return $this;
        }

        //
        // Proxy setter method for current locale
        $entityTranslation = $this->translate();
        if(property_exists($entityTranslation, $property)) {

            if (empty($arguments))
                throw new \InvalidArgumentException("Missing argument for setter property \"$property\" in ". get_class($entityTranslation));
            if (!$accessor->isWritable($entityTranslation, $property))
                throw new \BadMethodCallException("Property \"$property\" not writable in ". get_class($entityTranslation));

            $accessor->setValue($entityTranslation, $property, ...$arguments);
            return $this;
        }
    }
    
    public function __get($property)
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        //
        // Getter method in called class
        $entity = $this;
        if(method_exists($entity, $property))
            return $entity->{$property}();
        else if (method_exists($entity, "get".ucfirst($property)))
            return $entity->{"get".ucfirst($property)}();
        else if (property_exists($entity, $property) && $accessor->isReadable($entity, $property)) 
            return $accessor->getValue($entity, $property);

        //
        // Proxy getter method for current locale
        $defaultLocale = BaseService::getLocaleProvider()->getDefaultLocale();
        $entityTranslation = $this->translate();

        $value = null;
        if(method_exists($entityTranslation, $property))
            $value = $entityTranslation->{$property}();
        else if (method_exists($entityTranslation, "get".ucfirst($property)))
            $value = $entityTranslation->{"get".ucfirst($property)}();
        else if (property_exists($entityTranslation, $property) && $accessor->isReadable($entityTranslation, $property))
            $value = $accessor->getValue($entityTranslation, $property);

        // If current locale is empty.. then try to access value from default locale
        // (unless is was already the default locale)
        if ($value !== null) return $value;

        //
        // Proxy getter method for default locale
        if ($entityTranslation->getLocale() == $defaultLocale) return $value;
        else {

            $entityTranslation = $this->translate($defaultLocale);
            if(method_exists($entityTranslation, $property))
                return $entityTranslation->{$property}();
            else if(method_exists($entityTranslation, "get".ucfirst($property)))
                return $entityTranslation->{"get".ucfirst($property)}();
            else if (property_exists($entityTranslation, $property) && $accessor->isReadable($entityTranslation, $property)) 
                return $accessor->getValue($entityTranslation, $property);
        }

        throw new \BadMethodCallException("Can't get a way to read property \"$property\" in class \"".get_class($this)."\" or its corresponding translation class \"".$this->getTranslationEntityClass()."\".");
    }
}
