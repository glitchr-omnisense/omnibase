<?php

namespace Base\Bridge\Doctrine;

use Base\Database\Repository\Entities;
use Base\Database\Repository\Repositories;
use Base\Database\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Base\Database\Repository\Repositories over Doctrine's entity manager
 * (audit "lesringostars face au master plan", step R28): its repositories as
 * they are when they are omnibase's (Entities already), a plain
 * EntityRepository behind DoctrineEntities otherwise. byIds() and oneById()
 * ask what Base\Field\Type\SelectType asked before: the same queries.
 */
final class DoctrineRepositories implements Repositories
{
    /** @var array<class-string, Entities<object>> */
    private array $adapters = [];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function of(string $class): Entities
    {
        $repository = $this->entityManager->getRepository($class);
        if ($repository instanceof Entities) {
            return $repository;
        }

        return $this->adapters[$class] ??= new DoctrineEntities($this->entityManager, $repository);
    }

    public function byIds(string $class, array $ids): array
    {
        $repository = $this->entityManager->getRepository($class);
        if ($repository instanceof ServiceEntityRepository) {
            return $repository->cacheById($ids, [])->getResult();
        }

        return $repository->findBy([$this->entityManager->getClassMetadata($class)->getSingleIdentifierFieldName() => $ids]);
    }

    public function oneById(string $class, mixed $id): ?object
    {
        $repository = $this->entityManager->getRepository($class);
        if ($repository instanceof ServiceEntityRepository) {
            return $repository->cacheOneById($id);
        }

        return $repository->find($id);
    }
}
