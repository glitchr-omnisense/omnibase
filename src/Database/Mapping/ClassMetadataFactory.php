<?php

namespace Base\Database\Mapping;

use Base\Database\Event\ResolveDiscriminatorEventArgs;
use Base\Database\Events;
use Base\Exception\MissingDiscriminatorMapException;
use Base\Exception\MissingDiscriminatorValueException;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Exception;
use ReflectionException;

use Doctrine\ORM\Mapping\ClassMetadataFactory as DoctrineClassMetadataFactory;
use Doctrine\Persistence\Mapping\ReflectionService;
use Doctrine\Persistence\Proxy;

class ClassMetadataFactory extends DoctrineClassMetadataFactory
{
    protected function doLoadMetadata($class, $parent, $rootEntityFound, array $nonSuperclassParents): void
    {
        $class = $this->resolveDiscriminator($class);
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

    protected $uniqueTableName = [];
    protected function initializeReflection(ClassMetadataInterface $class, ReflectionService $reflService): void
    {
        parent::initializeReflection($class, $reflService);

        $className = $class->getName();
        $tableName = $class->getTableName();

        if (str_contains($className, "\\Entity\\") && array_key_exists($tableName, $this->uniqueTableName) && $className != $this->uniqueTableName[$tableName]) {
            throw new Exception("Ambiguous table name \"" . $tableName . "\" found between \"" . $this->uniqueTableName[$tableName] . "\" and \"" . $className . "\"");
        }

        $this->uniqueTableName[$tableName] = $className;
    }

    /**
     * Re-inject namingStrategy after deserialization from the metadata cache pool.
     *
     * Doctrine ORM 3.6 promoted ClassMetadata::$namingStrategy to a typed property
     * (protected NamingStrategy $namingStrategy;) but did NOT add it to __sleep()
     * — see vendor/doctrine/orm/src/Mapping/ClassMetadata.php::__sleep() around
     * line 723: the serialized field list excludes namingStrategy. ClassMetadata
     * also has no __wakeup() that re-initializes it, and Doctrine's own
     * AbstractClassMetadataFactory::wakeupReflection() only restores the
     * ReflectionService. So a cache-roundtripped ClassMetadata comes back with
     * an uninitialized typed property — the next code that touches it (e.g.
     * mapField → validateAndCompleteFieldMapping accessing
     * $this->namingStrategy->propertyToColumnName at ClassMetadata.php:1219)
     * throws "Typed property … namingStrategy must not be accessed before
     * initialization".
     *
     * This manifests in this project because Base\Cache\Warmer\MetadataCacheWarmer
     * → ClassMetadataManipulator::enrichAndSaveCompletors() iterates entities,
     * resolves their ClassMetadata via $em->getClassMetadata() (a cache-pool-
     * backed call), then invokes annotation->loadClassMetadata() which (in
     * Base\Database\Annotation\OrderColumn::loadClassMetadata at line 110) calls
     * $classMetadata->mapField([...]) — that's the access path that trips the
     * typed-property guard on a deserialized ClassMetadata.
     *
     * Re-injecting namingStrategy at wakeupReflection time fixes every cache-hit
     * path: subsequent property accesses succeed because the property is
     * initialized to the same NamingStrategy instance the constructor would
     * have set. Uses reflection because the property is `protected`.
     */
    protected function wakeupReflection(ClassMetadataInterface $class, ReflectionService $reflService): void
    {
        parent::wakeupReflection($class, $reflService);

        if (!$class instanceof ClassMetadata) {
            return;
        }

        $reflProperty = new \ReflectionProperty(ClassMetadata::class, 'namingStrategy');
        if (!$reflProperty->isInitialized($class)) {
            $reflProperty->setValue(
                $class,
                $this->em->getConfiguration()->getNamingStrategy()
            );
        }
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

    protected function resolveDiscriminator(ClassMetadata $class): ClassMetadata
    {
        // Dispatch custom discriminator resolver event
        $dispatcher = $this->em->getEventManager();

        if ($dispatcher->hasListeners(Events::resolveDiscriminator)) {
            $eventArgs = new ResolveDiscriminatorEventArgs($class, $this->em);
            $dispatcher->dispatchEvent(Events::resolveDiscriminator, $eventArgs);
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
