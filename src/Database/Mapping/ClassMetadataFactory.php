<?php

namespace Base\Database\Mapping;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;

use Doctrine\ORM\Mapping\ClassMetadataFactory as DoctrineClassMetadataFactory;
use Doctrine\Persistence\Mapping\ReflectionService;
use Doctrine\Persistence\Proxy;

/**
 * Doctrine's ClassMetadataFactory, as the applications name it in their
 * doctrine.yaml (class_metadata_factory_name), on doctrine/orm as published:
 * it uses nothing Doctrine keeps private (plan "omnibase agnostique", C6).
 *
 * What it did on a doctrine/orm that glitchr/base-plugin patched - the
 * `resolveDiscriminator` event before a class is read, the table names
 * checked, the discriminator maps keyed by class name through an overridden
 * getShortName() - is Base\Bridge\Doctrine\MappingDriver's, the driver the
 * bundle wraps every entity manager's with, and which an entity manager built
 * by hand gets from Base\Bridge\Doctrine\Mapping::register(), on Doctrine's own
 * factory. Kept here: the naming strategy given back to a ClassMetadata read
 * from the cache, getAllClassNames() and isEntity().
 */
class ClassMetadataFactory extends DoctrineClassMetadataFactory
{
    /** Doctrine's own is private. */
    protected ?EntityManagerInterface $entityManager = null;

    public function setEntityManager(EntityManagerInterface $em): void
    {
        parent::setEntityManager($em);
        $this->entityManager = $em;
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
     * backed call), then invokes attribute->loadClassMetadata() which (in
     * Base\Database\Attribute\OrderColumn::loadClassMetadata at line 110) calls
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
                $this->entityManager->getConfiguration()->getNamingStrategy()
            );
        }
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

        return !$this->entityManager->getMetadataFactory()->isTransient($class);
    }
}
