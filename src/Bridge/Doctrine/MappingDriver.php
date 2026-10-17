<?php

namespace Base\Bridge\Doctrine;

use Base\Database\Event\ResolveDiscriminatorEventArgs;
use Base\Database\Events;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver as PersistenceMappingDriver;

/**
 * omnibase's mapping driver: the application's driver (attributes, a chain of
 * them under DoctrineBundle), and around it what omnibase's ClassMetadataFactory
 * did on a patched doctrine/orm (plan "omnibase agnostique", C6):
 *
 * - before the driver, the `resolveDiscriminator` event (Base\Database\Events):
 *   IntlSubscriber gives a translation class the inheritance and the
 *   discriminator map of its translatable; then the discriminator value of a
 *   concrete class is looked up in its map, as the factory did;
 * - the table names checked: two entity classes may not take the same one;
 * - after the driver, the discriminator map of a root entity that declares an
 *   inheritance and no map is generated: the class and every subclass the
 *   driver knows, each under its own class name. Doctrine would generate it
 *   itself, under the classes' short names - which collide here (App\Entity\
 *   Thread\Tag, Base\Entity\Thread\Tag) - and its getShortName() is private:
 *   glitchr/base-plugin made it protected so that the factory could answer the
 *   class name. The map is now there before Doctrine looks, its keys those the
 *   factory gave, and #[DiscriminatorEntry] (AttributeSubscriber,
 *   loadClassMetadata) renames them as before.
 *
 * Under Symfony the bundle wraps every entity manager's driver with it
 * (DoctrineConfigurationPass); Base\Bridge\Doctrine\Mapping::register() does
 * it for an entity manager built by hand. Doctrine's own ClassMetadataFactory
 * is then enough: nothing here needs a member Doctrine keeps private.
 */
final class MappingDriver implements PersistenceMappingDriver
{
    /** @var array<string, string> table name => class name */
    private array $tables = [];

    private ?EntityManagerInterface $resolvedEntityManager = null;

    /** @param EntityManagerInterface|\Closure(): EntityManagerInterface|null $entityManager */
    public function __construct(
        private readonly PersistenceMappingDriver $driver,
        private EntityManagerInterface|\Closure|null $entityManager = null,
    ) {
    }

    public function getDriver(): PersistenceMappingDriver
    {
        return $this->driver;
    }

    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
        $this->resolvedEntityManager = $entityManager;
    }

    public function loadMetadataForClass(string $className, PersistenceClassMetadata $metadata): void
    {
        if ($metadata instanceof ClassMetadata) {
            $this->checkTableName($metadata);
            $this->resolveDiscriminator($metadata);
        }

        $this->driver->loadMetadataForClass($className, $metadata);

        if ($metadata instanceof ClassMetadata) {
            $this->generateDiscriminatorMap($metadata);
        }
    }

    /**
     * The driver's classes, without the aliases: an attribute driver lists the classes PHP declared from its
     * folders, and PHP lists a class_alias() among them, lower-cased (app\entity\thread\tag for App\Entity\
     * Thread\Tag, Base\Entity\Thread\Tag's alias). DoctrineBundle's chain dropped them by its namespaces' case;
     * one attribute driver over every folder (an entity manager built by hand) did not.
     *
     * @return list<class-string>
     */
    public function getAllClassNames(): array
    {
        return array_values(array_filter(
            $this->driver->getAllClassNames(),
            static fn (string $className): bool => !class_exists($className, false) || (new \ReflectionClass($className))->getName() === $className,
        ));
    }

    public function isTransient(string $className): bool
    {
        return $this->driver->isTransient($className);
    }

    /** What else the application's driver answers (MappingDriverChain::getDrivers()...). */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->driver->$method(...$arguments);
    }

    private function entityManager(): ?EntityManagerInterface
    {
        if ($this->resolvedEntityManager === null && $this->entityManager instanceof \Closure) {
            $this->resolvedEntityManager = ($this->entityManager)();
        } elseif ($this->resolvedEntityManager === null) {
            $this->resolvedEntityManager = $this->entityManager;
        }

        return $this->resolvedEntityManager;
    }

    /** The table name a class is given before its mapping is read (the naming strategy's), one class each. */
    private function checkTableName(ClassMetadata $metadata): void
    {
        $className = $metadata->getName();
        $tableName = $metadata->getTableName();

        if (str_contains($className, "\\Entity\\") && array_key_exists($tableName, $this->tables) && $className != $this->tables[$tableName]) {
            throw new \Exception("Ambiguous table name \"".$tableName."\" found between \"".$this->tables[$tableName]."\" and \"".$className."\"");
        }

        $this->tables[$tableName] = $className;
    }

    /**
     * The `resolveDiscriminator` event, then the discriminator value of a concrete class found in the map it was
     * given: what Base\Database\Mapping\ClassMetadataFactory::resolveDiscriminator() did, before the class was read.
     */
    private function resolveDiscriminator(ClassMetadata $class): void
    {
        $entityManager = $this->entityManager();
        if ($entityManager === null) {
            return;
        }

        $dispatcher = $entityManager->getEventManager();
        if ($dispatcher->hasListeners(Events::resolveDiscriminator)) {
            $dispatcher->dispatchEvent(Events::resolveDiscriminator, new ResolveDiscriminatorEventArgs($class, $entityManager));
        }

        if ($class->discriminatorValue || !$class->discriminatorMap
            || $class->isMappedSuperclass || !$class->reflClass || $class->reflClass->isAbstract()) {
            return;
        }

        foreach ($class->discriminatorMap as $discriminatorValue => $discriminatorClass) {
            if ($discriminatorClass === $class->name) {
                $class->discriminatorValue = $discriminatorValue;

                return;
            }
        }

        foreach ($class->discriminatorMap as $discriminatorValue => $discriminatorClass) {
            if ($class->name === $entityManager->getMetadataFactory()->getMetadataFor($discriminatorClass)->getName()) {
                $class->discriminatorValue = $discriminatorValue;

                return;
            }
        }

        throw MappingException::mappedClassNotPartOfDiscriminatorMap($class->name, $class->rootEntityName);
    }

    /**
     * A root entity with an inheritance and no map (a subclass has its parent's by now): the class and its
     * subclasses among the driver's, each under its class name - Doctrine's default map, with the keys the
     * factory's getShortName() gave.
     */
    private function generateDiscriminatorMap(ClassMetadata $class): void
    {
        if ($class->isMappedSuperclass || $class->isEmbeddedClass || $class->isInheritanceTypeNone() || $class->discriminatorMap) {
            return;
        }

        $map = [$class->name => $class->name];
        foreach ($this->getAllClassNames() as $subClassCandidate) {
            if (is_subclass_of($subClassCandidate, $class->name)) {
                $map[$subClassCandidate] = $subClassCandidate;
            }
        }

        $class->setDiscriminatorMap($map);
    }
}
