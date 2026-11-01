<?php

namespace Base\Bridge\Doctrine;

use Base\Bridge\Doctrine\Behaviour\DoctrineBehaviours;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork as DoctrineUnitOfWorkState;
use Omnibridge\Persistence\UnitOfWork;

/**
 * omnisense/omnibridge's UnitOfWork over a Doctrine entity manager (plan "omnibase agnostic", step
 * F2): what the core's code asks where it spoke to the entity manager, and what the lifecycle events
 * the Doctrine relay dispatches carry. Moves to omnibridge/doctrine at step F3.
 */
final class DoctrineUnitOfWork implements UnitOfWork
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        // Its backends (repositories, shapes, the behaviours' parts) are there for any unit of work made.
        DoctrineBehaviours::ensureRegistered();
    }

    public function entityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function persist(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->entityManager->remove($entity);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function refresh(object $entity): void
    {
        $this->entityManager->refresh($entity);
    }

    public function contains(object $entity): bool
    {
        return $this->entityManager->contains($entity);
    }

    public function reference(string $class, mixed $id): object
    {
        return $this->entityManager->getReference($class, $id);
    }

    public function transactional(callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(fn () => $work($this));
    }

    public function isNew(object $entity): bool
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();

        // Persisted and not flushed yet has no row either.
        return $unitOfWork->isScheduledForInsert($entity)
            || DoctrineUnitOfWorkState::STATE_NEW === $unitOfWork->getEntityState($entity, DoctrineUnitOfWorkState::STATE_NEW);
    }

    public function identifier(object $entity): mixed
    {
        $values = $this->entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity);

        return match (\count($values)) {
            0 => null,
            1 => reset($values),
            default => $values,
        };
    }

    public function className(object $entity): string
    {
        return $this->entityManager->getClassMetadata($entity::class)->getName();
    }

    public function changes(object $entity): array
    {
        return $this->entityManager->getUnitOfWork()->getEntityChangeSet($entity);
    }

    /** Doctrine's recomputeSingleEntityChangeSet(): for an entity a listener changed during a flush. */
    public function recompute(object $entity): void
    {
        $this->entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet($this->entityManager->getClassMetadata($entity::class), $entity);
    }
}
