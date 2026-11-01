<?php

namespace Base\Bridge\Doctrine\Behaviour;

use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Backend\RowLocks;
use Doctrine\DBAL\LockMode;

/** RowLocks on Doctrine: a pessimistic write lock in a transaction (plan "omnibase agnostic", step F2d; moves to omnibridge/doctrine at F3). */
final class DoctrineRowLocks implements RowLocks
{
    public function __construct(private readonly DoctrineUnitOfWork $unitOfWork)
    {
    }

    public function locked(object $entity, callable $work): mixed
    {
        $entityManager = $this->unitOfWork->entityManager();
        $entityManager->beginTransaction();
        try {
            $entityManager->lock($entity, LockMode::PESSIMISTIC_WRITE);
            $result = $work();
            $entityManager->commit();

            return $result;
        } catch (\Throwable $e) {
            $entityManager->rollback();

            throw $e;
        }
    }
}
