<?php

namespace Base\Database\Mapping;

use Base\Database\Mapping\NamingStrategy;
use Base\Database\TranslatableInterface;
use Base\Database\TranslationInterface;

use Base\Exception\MissingDiscriminatorMapException;
use Base\Exception\MissingDiscriminatorValueException;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Exception;
use ReflectionException;

use Doctrine\ORM\Mapping\ClassMetadataFactory as DoctrineClassMetadataFactory;

class ClassMetadataFactory extends DoctrineClassMetadataFactory
{
    protected function doLoadMetadata($class, $parent, $rootEntityFound, array $nonSuperclassParents): void
    {
        $class = $this->resolveDiscriminatorValue($class);
        parent::doLoadMetadata($class, $parent, $rootEntityFound, $nonSuperclassParents);
    }
    
    /**
     * @return string[]
     */
    public function getAllClassNames()
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $driver = $this->getDriver();
        return $driver->getAllClassNames();
    }


    /**
     * Populates the discriminator value of the given metadata (if not set) by iterating over discriminator
     * map classes and looking for a fitting one.
     *
     * @param ClassMetadata $classMetadata
     * @return ClassMetadata
     *
     * @throws MappingException
     * @throws MissingDiscriminatorMapException
     * @throws MissingDiscriminatorValueException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */

    protected function resolveDiscriminatorValue(ClassMetadata $class): ClassMetadata
    {
        //If translatable object: preprocess inheritanceType, discriminatorMap, discriminatorColumn, discriminatorValue
        if (is_subclass_of($class->getName(), TranslationInterface::class)) {

            if (!str_ends_with($class->getName(), NamingStrategy::TABLE_I18N_SUFFIX)) {
                throw new Exception("Invalid class name for \"" . $class->getName() . "\"");
            }

            $translatableClass = $class->getName()::getTranslatableEntityClass();
            $translatableMetadata = $this->getMetadataFor($translatableClass);

            //
            // Handle translation discriminator map
            if (!$class->discriminatorMap) {
                $class->discriminatorMap = array_filter(array_map(function ($className) {
                    return (is_subclass_of($className, TranslatableInterface::class))
                        ? $className::getTranslationEntityClass(false)
                        : null;
                }, $translatableMetadata->discriminatorMap), fn($c) => $c !== null);
            }

            //
            // Handle translation subclasses
            $subClasses = [];
            foreach ($translatableMetadata->subClasses as $translatableSubclass) {
                $translationClass = $translatableSubclass::getTranslationEntityClass();
                if ($translationClass !== null && $translationClass != $class->getName()) {
                    $subClasses[] = $translationClass;
                }
            }

            // Apply values..
            $class->subClasses = array_unique($subClasses);
            $class->inheritanceType = $translatableMetadata->inheritanceType;
            $class->discriminatorColumn = $translatableMetadata->discriminatorColumn;
            if ($class->discriminatorMap) {
                if (!in_array($class->getName(), $class->discriminatorMap)) {
                    throw new MissingDiscriminatorMapException(
                        "Discriminator map missing for \"" . $class->getName() .
                        "\". Did you forgot to implement \"" . TranslatableInterface::class .
                        "\" in \"" . $class->getName()::getTranslatableEntityClass() . "\"."
                    );
                }

                $class->discriminatorValue = array_flip($translatableMetadata->discriminatorMap)[$translatableMetadata->getName()] ?? null;
                if (!$class->discriminatorValue) {
                    throw new MissingDiscriminatorValueException("Discriminator value missing for \"" . $class->getName() . "\".");
                }
            }
        }

        if ($class->discriminatorValue || !$class->discriminatorMap ||
            $class->isMappedSuperclass || !$class->reflClass || $class->reflClass->isAbstract()) {
            return $class;
        }

        // minor optimization: avoid loading related metadata when not needed
        foreach ($class->discriminatorMap as $discriminatorValue => $discriminatorClass) {
            if ($discriminatorClass === $class->name) {
                $class->discriminatorValue = $discriminatorValue;
                return $class;
            }
        }

        // iterate over discriminator mappings and resolve actual referenced classes according to existing metadata
        foreach ($class->discriminatorMap as $discriminatorValue => $discriminatorClass) {
            if ($class->name === $this->getMetadataFor($discriminatorClass)->getName()) {
                $class->discriminatorValue = $discriminatorValue;
                return $class;
            }
        }

        throw MappingException::mappedClassNotPartOfDiscriminatorMap($class->name, $class->rootEntityName);
    }

    /**
     * Gets the lower-case short name of a class.
     *
     * @psalm-param class-string $className
     */
    protected function getShortName(string $className): string
    {
        
        return $className;
    }

    /**
     * {@inheritDoc}
     */
    public function isEntity($class): bool
    {
        if ($class instanceof ClassMetadataInterface) {
            return isset($class->isMappedSuperclass) && $class->isMappedSuperclass === false;
        } elseif (is_object($class)) {
            $class = ($class instanceof Proxy) ? get_parent_class($class) : get_class($class);
        }

        return !$this->em->getMetadataFactory()->isTransient($class);
    }
}
