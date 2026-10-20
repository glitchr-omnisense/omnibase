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
 *
 * A repository that takes other services beside the registry (a site's
 * CommentRepository, its cache pool first: `__construct(CacheItemPoolInterface
 * $cache, ManagerRegistry $doctrine, ?string $entityName = null)`) is given
 * each of them by its type from the host (services(): a closure, the host's
 * container - Mapping::register()'s option `services`); a service the host does
 * not have is null where the parameter allows it, its default where it has
 * one, else the repository is refused with the service it lacks.
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
    /** @var (\Closure(class-string): ?object)|null the host's services by type */
    private ?\Closure $services = null;

    public function __construct(private readonly ?ManagerRegistry $registry = null)
    {
    }

    /** @param \Closure(class-string): ?object $services a service of the host by its type, null when it has none */
    public function services(\Closure $services): void
    {
        $this->services = $services;
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

        $registries = array_filter($constructor?->getParameters() ?? [], static fn (\ReflectionParameter $parameter) => ($t = $parameter->getType()) instanceof \ReflectionNamedType && !$t->isBuiltin() && is_a($t->getName(), ManagerRegistry::class, true));
        if ($registries) {
            return new $className(...array_map(fn (\ReflectionParameter $parameter) => $this->argument($parameter, $entityManager, $entityName, $className), $constructor->getParameters()));
        }

        return new $className($entityManager, $metadata);
    }

    /** One argument of a repository built from the registry and services of the host. */
    private function argument(\ReflectionParameter $parameter, EntityManagerInterface $entityManager, string $entityName, string $className): mixed
    {
        $type = $parameter->getType();
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            if (is_a($type->getName(), ManagerRegistry::class, true)) {
                return $this->registryFor($entityManager);
            }
            if (is_a($type->getName(), EntityManagerInterface::class, true)) {
                return $entityManager;
            }
            $service = null !== $this->services ? ($this->services)($type->getName()) : null;
            if (null !== $service) {
                return $service;
            }
        } elseif ('entityName' === $parameter->getName()) {
            return $entityName;
        }
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        if ($parameter->allowsNull()) {
            return null;
        }

        throw new \LogicException(\sprintf('The repository %s asks %s $%s, which the host does not give (Mapping::register()\'s option "services").', $className, $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed', $parameter->getName()));
    }
}
