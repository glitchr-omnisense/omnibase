<?php

namespace Base\Bridge\Doctrine;

use Base\Database\Repository\Entities;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Base\Database\Repository\Entities over a plain Doctrine EntityRepository
 * (an entity that names no omnibase repository: the translations, the
 * envelopes...), as ServiceEntityRepository answers it for omnibase's: its
 * finders as they are, the entity manager's unit of work.
 *
 * @template T of object
 *
 * @implements Entities<T>
 */
final class DoctrineEntities implements Entities
{
    /** @param EntityRepository<T> $repository */
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly EntityRepository $repository)
    {
    }

    public function find($id): ?object
    {
        return $this->repository->find($id);
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
    {
        return $this->repository->findBy($criteria, $orderBy, $limit, $offset);
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        return $this->repository->findOneBy($criteria, $orderBy);
    }

    public function count(array $criteria = []): int
    {
        return $this->repository->count($criteria);
    }

    public function persist($entity, bool $flush = false): void
    {
        $this->entityManager->persist($entity);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function remove($entity, bool $flush = false): void
    {
        $this->entityManager->remove($entity);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function flush(bool $autoclear = false)
    {
        $this->entityManager->flush();
        if ($autoclear) {
            $this->entityManager->clear();
        }
    }
}
