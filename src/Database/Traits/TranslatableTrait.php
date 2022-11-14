<?php

namespace Base\Database\Traits;

use App\Entity\Marketplace\Product\Extra\Wallpaper;
use Base\Database\Mapping\NamingStrategy;
use Base\Database\TranslationInterface;

use Base\Service\BaseService;
use Base\Service\LocaleProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Exception;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\Exception\AccessException;

trait TranslatableTrait
{
    private static $translationClass;
    public static function getEntityFqcn(): string { return self::getTranslationEntityClass()::getTranslatableEntityClass(); }
    public static function getTranslationEntityClass(
        bool $withInheritance = true, // This is required in some cases, where you must access main class without inheritance
        bool $selfClass = false // Proxies\__CG__ error, if not true during discriminator map building (TranslationType)
    ): ?string
    {
        $class = ($selfClass ? self::class : static::class);

        $prefix = "Proxies\__CG__\\";
        if (strpos($class, $prefix) === 0)
            $class = substr($class, strlen($prefix));

        if($withInheritance) {

            self::$translationClass = $class . NamingStrategy::TABLE_I18N_SUFFIX;
            while(!class_exists(self::$translationClass) || !is_subclass_of(self::$translationClass, TranslationInterface::class)) {

                if(!get_parent_class($class)) throw new Exception("No translation entity found for ".$class);

                $class = get_parent_class($class);
                self::$translationClass = $class . NamingStrategy::TABLE_I18N_SUFFIX;
            }

            return self::$translationClass;
        }

        $translationClass = $class . NamingStrategy::TABLE_I18N_SUFFIX;
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

    public function removeTranslation(TranslationInterface $translation) {

        if ($this->getTranslations()->contains($translation)) {
            $this->getTranslations()->removeElement($translation);
        }

        return $this;
    }

    public function clearTranslations() {

        foreach($this->translations as $translation) {
            $this->translations->removeElement($translation);
        }

        return $this;
    }


    public function addTranslation(TranslationInterface $translation)
    {
        if($translation !== null) {

            $this->getTranslations()->set(LocaleProvider::normalize($translation->getLocale()), $translation);
            $translation->setTranslatable($this);
        }

        return $this;
    }

    public function translate(?string $locale = null)
    {
        $localeProvider = BaseService::getLocaleProvider();
        if(!$localeProvider) return null;

        $defaultLocale = $localeProvider->getDefaultLocale();
        $availableLocales = $localeProvider->getAvailableLocales();

        $locale = intval($locale) < 0 ? $defaultLocale : $locale;
        $normLocale = $localeProvider->getLocale($locale); // Locale normalizer
        $translationClass = self::getTranslationEntityClass(true, false);
        $translations = $this->getTranslations();

        $translation = $translations[$normLocale] ?? null;
        if(!$translation && $locale === null) {

            // First entry is default locale
            $locales = array_filter($translations->getKeys(), fn($l) => in_array($l, $availableLocales));
            foreach($locales as $locale) {

                $translation = $translations[$locale] ?? null;
                if($translation) break;
            }

            // Search for compatible lang
            if($translation == null) {

                $locales = array_filter($translations->getKeys(), fn($l) => !in_array($l, $availableLocales));
                $fallbackLocales = array_map(fn($l) => $localeProvider->getLocale($localeProvider->getLang($l)), $locales);

                foreach(array_keys($fallbackLocales, $normLocale) as $normKey)
                    $translation = $translations[$locales[$normKey]] ?? null;

                foreach($locales as $locale) {

                        $translation = $translations[$locale] ?? null;
                        if($translation) break;
                }
            }
        }

        // Create a new locale if still not found..
        if(!$translation) {

            $translation = new $translationClass;
            $translation->setLocale($normLocale);

            $this->addTranslation($translation);
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

            if (empty($arguments))
                throw new AccessException("Missing argument for setter property \"$property\" in ". $className);

            try { return $this->__set($property, ...$arguments); }
            catch (AccessException $e) {

                // Parent fallback setter
                if($parentClass && method_exists($parentClass, "__set"))
                    return parent::__set($property, ...$arguments);
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
            catch (AccessException $e)
            {
                // Parent fallback getter
                if($parentClass && method_exists($className, "__get"))
                    return parent::__get($property);
            }

        } else if($translationClassName && method_exists($translationClassName, $method)) {

            return $this->translate()->$method(...$arguments);
        }

        //
        // Parent fallback for magic __call
        if($parentClass && method_exists($parentClass,"__call"))
            return parent::__call($method, $arguments);

        if(!method_exists($className,$method))
            throw new AccessException("Method \"$method\" not found in class \"".get_class($this)."\" or its corresponding translation class \"".$this->getTranslationEntityClass()."\".");

        return null;
    }

    public function __set($property, $value)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $property = snake2camel($property);
        $entity = $this;

        //
        // Setter method in called class
        if (method_exists($entity, "set".mb_ucfirst($property))) {
            return $entity->{"set".mb_ucfirst($property)}($value);
        } else if(property_exists($this, $property)) {

            if (!$accessor->isWritable($this, $property))
                throw new AccessException("Property \"$property\" not writable in ". get_class($this));

            $accessor->setValue($this, $property, $value);
            return $this;
        }

        //
        // Proxy setter method for current locale
        $entityIntl = $this->translate();
        if (method_exists($entityIntl, "set".mb_ucfirst($property))) {
            return $entityIntl->{"set".mb_ucfirst($property)}($value);
        } else if(property_exists($entityIntl, $property)) {

            if (!$accessor->isWritable($entityIntl, $property))
                throw new AccessException("Property \"$property\" not writable in ". get_class($entityIntl));

            $accessor->setValue($entityIntl, $property, $value);
            return $this;

        }

        // Prevent "ea_" property exception conflict.. Damn'it.. ! >()
        if(str_starts_with($property, "ea_")) return $this;

        throw new AccessException("Can't get a way to write property \"$property\" in class \"".get_class($this)."\" or its corresponding translation class \"".$this->getTranslationEntityClass()."\".");
    }

    public function __get($property)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $property = snake2camel($property);

        //
        // Getter method in called class
        $entity = $this;
        if(method_exists($entity, $property))
            return $entity->{$property}();
        else if (method_exists($entity, "get".mb_ucfirst($property)))
            return $entity->{"get".mb_ucfirst($property)}();
        else if (property_exists($entity, $property) && $accessor->isReadable($entity, $property))
            return $accessor->getValue($entity, $property);

        //
        // Proxy getter method for current locale
        $defaultLocale = BaseService::getLocaleProvider()->getDefaultLocale();
        $entityIntl = $this->translate();

        $value = null;
        if(method_exists($entityIntl, $property))
            $value = $entityIntl->{$property}();
        else if (method_exists($entityIntl, "get".mb_ucfirst($property)))
            $value = $entityIntl->{"get".mb_ucfirst($property)}();
        else if (property_exists($entityIntl, $property) && $accessor->isReadable($entityIntl, $property))
            $value = $accessor->getValue($entityIntl, $property);

        // If current locale is empty.. then try to access value from default locale
        // (unless is was already the default locale)
        if ($value !== null) return $value;

        //
        // Proxy getter method for default locale
        if ($entityIntl->getLocale() == $defaultLocale)
            return $value;

        $entityIntl = $this->translate($defaultLocale);
        if(method_exists($entityIntl, $property))
            return $entityIntl->{$property}();
        else if(method_exists($entityIntl, "get".mb_ucfirst($property)))
            return $entityIntl->{"get".mb_ucfirst($property)}();
        else if (property_exists($entityIntl, $property) && $accessor->isReadable($entityIntl, $property))
            return $accessor->getValue($entityIntl, $property);

        // Exception for EA variables (cf. EA's FormField)
        if(str_starts_with($property, "ea_")) return null;

        throw new AccessException("Can't get a way to read property \"$property\" in class \"".get_class($this)."\" or its corresponding translation class \"".$this->getTranslationEntityClass()."\".");
    }
}
