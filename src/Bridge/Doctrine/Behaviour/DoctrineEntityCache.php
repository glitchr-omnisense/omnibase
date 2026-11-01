<?php

namespace Base\Bridge\Doctrine\Behaviour;

use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Backend\EntityCache;

/** EntityCache on Doctrine: its second-level cache, when enabled (plan "omnibase agnostic", step F2d; moves to omnibridge/doctrine at F3). */
final class DoctrineEntityCache implements EntityCache
{
    public function __construct(private readonly DoctrineUnitOfWork $unitOfWork)
    {
    }

    public function enabled(): bool
    {
        return null !== $this->unitOfWork->entityManager()->getCache();
    }

    public function evict(string $class, mixed $id): void
    {
        $this->unitOfWork->entityManager()->getCache()?->evictEntity($class, $id);
    }

    public function evictClass(string $class): void
    {
        $this->unitOfWork->entityManager()->getCache()?->evictEntityRegion($class);
    }
}
