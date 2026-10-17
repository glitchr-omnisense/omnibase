<?php

namespace Base\Bridge\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Repository\RepositoryFactory as RepositoryFactoryInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The repositories of an entity manager built by hand (Base\Bridge\Doctrine\
 * Mapping::getRepository(), or the configuration's repository factory when it
 * is set before the entity manager is built): a repository built from the
 * ManagerRegistry - omnibase's
 * ServiceEntityRepository and the applications' repositories over it,
 * `__construct(ManagerRegistry $registry, ?string $entityName = null)` - is
 * given the registry (and the entity's class when it takes one); any other is
 * built as Doctrine builds it, from the entity manager and the metadata. Under
 * Symfony, DoctrineBundle's ContainerRepositoryFactory hands out the services.
 */
final class RepositoryFactory implements RepositoryFactoryInterface
{
    /** @var array<string, EntityRepository> */
    private array $repositories = [];

    /** @var array<int, ManagerRegistry> a registry over each entity manager, when none was given */
    private array $registries = [];

    /**
     * The registry the repositories are built from; none: one over the entity
     * manager asking (Base\Bridge\Doctrine\Registry) - the factory is then set
     * on a configuration before its entity manager exists (Mapping::configure()).
     */
    public function __construct(private readonly ?ManagerRegistry $registry = null)
    {
    }

    /** The registry a repository of this entity manager is built from. */
    public function registryFor(EntityManagerInterface $entityManager): ManagerRegistry
    {
        return $this->registry ?? $this->registries[spl_object_id($entityManager)] ??= new Registry($entityManager);
    }

    public function getRepository(EntityManagerInterface $entityManager, string $entityName): EntityRepository
    {
        $metadata = $entityManager->getClassMetadata($entityName);
        $key = $metadata->getName().spl_object_id($entityManager);

        return $this->repositories[$key] ??= $this->create($entityManager, $metadata->getName());
    }

    private function create(EntityManagerInterface $entityManager, string $entityName): EntityRepository
    {
        $metadata = $entityManager->getClassMetadata($entityName);
        $className = $metadata->customRepositoryClassName ?: $entityManager->getConfiguration()->getDefaultRepositoryClassName();

        $constructor = (new \ReflectionClass($className))->getConstructor();
        $first = $constructor?->getParameters()[0] ?? null;
        $type = $first?->getType();
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && is_a($type->getName(), ManagerRegistry::class, true)) {
            $registry = $this->registryFor($entityManager);

            return $constructor->getNumberOfParameters() > 1 ? new $className($registry, $entityName) : new $className($registry);
        }

        return new $className($entityManager, $metadata);
    }
}
