<?php

namespace Base\Database\Repository;

use Base\Repository\Aliases;
use Psr\Container\ContainerInterface;

/**
 * Repositories on any persistence: the repository of an entity class - the one its mapping names (repositoryClass:
 * a class, or one of the core's repository interfaces, bound by Base\Repository\Aliases), else its nearest parent
 * entity's, else the one of its name (X\Entity\A\B: X\Repository\A\BRepository), else the plain
 * ServiceEntityRepository of the class. Every class gets the finder language.
 *
 * A repository the host registered as a service (the locator given: Symfony's container, Laravel's) is that
 * service when it is the class's own; any other is built here, its constructor given the Finders, the entity's
 * class ($entityName) and the host's services by their types.
 */
final class EntityRepositories implements Repositories
{
    /** The mapping attributes that name a repository, read by name: no ORM is loaded. */
    private const ATTRIBUTES = ['Base\Mapping\Entity', 'Base\Mapping\MappedSuperclass', 'Doctrine\ORM\Mapping\Entity', 'Doctrine\ORM\Mapping\MappedSuperclass'];

    /** @var array<string, ServiceEntityRepository<object>> */
    private array $repositories = [];

    /** @var array<string, class-string<ServiceEntityRepository<object>>> */
    private array $classes = [];

    public function __construct(private readonly Finders $finders, private readonly ?ContainerInterface $services = null)
    {
    }

    public function of(string $class): Entities
    {
        return $this->repository($class);
    }

    public function byIds(string $class, array $ids): array
    {
        return [] === $ids ? [] : $this->repository($class)->cacheById($ids)->getResult();
    }

    public function oneById(string $class, mixed $id): ?object
    {
        return null === $id ? null : $this->repository($class)->cacheOneById($id);
    }

    /**
     * of()'s repository as what it is here: a ServiceEntityRepository, which speaks the finder language.
     *
     * @return ServiceEntityRepository<object>
     */
    private function repository(string $class): ServiceEntityRepository
    {
        $class = (new \ReflectionClass($class))->getName();

        return $this->repositories[$class] ??= $this->make($class);
    }

    /**
     * The repository behind one of the core's repository interfaces (Threads, Users, Comments...:
     * Base\Repository\Aliases), for a host without a container of its own to bind them in.
     *
     * @template T of Entities
     *
     * @param class-string<T>   $interface
     * @param class-string|null $class     the entity to read, the bound repository's own by default
     *
     * @return T
     */
    public function implementing(string $interface, ?string $class = null): Entities
    {
        $bound = Aliases::INTERFACES[$interface] ?? throw new \OutOfBoundsException(sprintf('No repository is bound to %s (Base\Repository\Aliases).', $interface));
        // The application's (App\Repository\UserRepository), else the core's under that name.
        if (!class_exists($bound) && str_starts_with($bound, 'App\\')) {
            $bound = 'Base\\'.substr($bound, 4);
        }
        // @phpstan-ignore function.alreadyNarrowedType (Aliases binds repositories; an application's App\ class is checked at run time)
        if (!class_exists($bound) || !is_a($bound, ServiceEntityRepository::class, true)) {
            throw new \LogicException(sprintf('%s, bound to %s, is no repository of omnibase.', $bound, $interface));
        }
        $class ??= $bound::getFqcnEntityName();
        $repository = $this->of($class);
        if (!$repository instanceof $interface) {
            // The entity's mapping names another repository than the bound one: the bound one, on that entity.
            $repository = $this->repositories[$interface."\0".$class] ??= $this->build((new \ReflectionClass($bound))->getName(), (new \ReflectionClass($class))->getName());
        }

        return $repository;
    }

    /**
     * An application's repository for an entity and those of its subclasses that name none, in place of the one
     * found: a host that knows better than the mapping (a test, a persistence switched per class).
     *
     * @param class-string                           $entity
     * @param class-string<ServiceEntityRepository<object>> $repository
     */
    public function register(string $entity, string $repository): void
    {
        // @phpstan-ignore function.alreadyNarrowedType (a caller's class-string is checked at run time all the same)
        if (!is_a($repository, ServiceEntityRepository::class, true)) {
            throw new \InvalidArgumentException(sprintf('%s is no repository of omnibase (%s).', $repository, ServiceEntityRepository::class));
        }
        $this->classes[(new \ReflectionClass($entity))->getName()] = $repository;
        $this->repositories = [];
    }

    /** @return class-string<ServiceEntityRepository<object>> the repository class of an entity class */
    public function repositoryClass(string $class): string
    {
        $class = (new \ReflectionClass($class))->getName();
        for ($candidate = $class; false !== $candidate; $candidate = get_parent_class($candidate)) {
            $found = $this->classes[$candidate] ?? self::declared($candidate) ?? self::named($candidate);
            if (null !== $found) {
                return $found;
            }
        }

        return ServiceEntityRepository::class;
    }

    /** The repository an entity's mapping attribute names, null when it names none or one that is not omnibase's. */
    private static function declared(string $class): ?string
    {
        foreach ((new \ReflectionClass($class))->getAttributes() as $attribute) {
            if (!\in_array($attribute->getName(), self::ATTRIBUTES, true)) {
                continue;
            }
            $arguments = $attribute->getArguments();
            $named = $arguments['repositoryClass'] ?? $arguments[0] ?? null;
            if (!\is_string($named)) {
                continue;
            }
            if (interface_exists($named)) {
                $named = Aliases::INTERFACES[$named] ?? null;
            }
            if (null !== $named && class_exists($named) && is_a($named, ServiceEntityRepository::class, true)) {
                return (new \ReflectionClass($named))->getName();
            }
        }

        return null;
    }

    /** The repository of the entity's name (X\Entity\A\B: X\Repository\A\BRepository), when that class is one. */
    private static function named(string $class): ?string
    {
        if (!str_contains($class, '\\Entity\\')) {
            return null;
        }
        $named = str_replace('\\Entity\\', '\\Repository\\', $class).'Repository';

        return class_exists($named) && is_a($named, ServiceEntityRepository::class, true) && !(new \ReflectionClass($named))->isAbstract() ? $named : null;
    }

    /** @return ServiceEntityRepository<object> */
    private function make(string $class): ServiceEntityRepository
    {
        $repository = $this->repositoryClass($class);
        if ((new \ReflectionClass($repository))->isAbstract()) {
            $repository = ServiceEntityRepository::class;
        }
        if (ServiceEntityRepository::class !== $repository && null !== $this->services && $this->services->has($repository)) {
            $service = $this->services->get($repository);
            if ($service instanceof ServiceEntityRepository && $service->getClassName() === $class) {
                return $service;
            }
        }

        return $this->build($repository, $class);
    }

    /** @return ServiceEntityRepository<object> */
    private function build(string $repository, string $class): ServiceEntityRepository
    {
        $arguments = [];
        foreach ((new \ReflectionClass($repository))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $arguments[] = $this->argument($parameter, $class, $repository);
        }

        return new $repository(...$arguments);
    }

    private function argument(\ReflectionParameter $parameter, string $class, string $repository): mixed
    {
        $type = $parameter->getType();
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            if (is_a($type->getName(), Finders::class, true)) {
                return $this->finders;
            }
            // What the core owns, whatever the host's container holds: these repositories, the unit of work.
            if (is_a($this, $type->getName())) {
                return $this;
            }
            if (is_a($type->getName(), \Omnibridge\Persistence\UnitOfWork::class, true)) {
                try {
                    return $this->finders->unitOfWork();
                } catch (\Throwable $exception) {
                    // Finders made to read only: null where the repository takes none, else the reason.
                    if (!$parameter->allowsNull()) {
                        throw $exception;
                    }

                    return null;
                }
            }
            if (null !== $this->services && $this->services->has($type->getName())) {
                return $this->services->get($type->getName());
            }
            // Another repository (an interface of the core's, Base\Repository\ThreadTranslations, or a repository
            // class): these repositories give it, on the same Finders.
            if (interface_exists($type->getName()) && isset(\Base\Repository\Aliases::INTERFACES[$type->getName()])) {
                return $this->implementing($type->getName());
            }
            if (is_a($type->getName(), ServiceEntityRepository::class, true) && !(new \ReflectionClass($type->getName()))->isAbstract()) {
                return $this->build($type->getName(), $type->getName()::getFqcnEntityName());
            }
        } elseif ('entityName' === $parameter->getName()) {
            return $class;
        }
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        if ($parameter->allowsNull()) {
            return null;
        }

        throw new \LogicException(sprintf('The repository %s asks %s $%s, which the host does not give.', $repository, $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed', $parameter->getName()));
    }
}
