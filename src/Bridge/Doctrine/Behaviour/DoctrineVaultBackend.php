<?php

namespace Base\Bridge\Doctrine\Behaviour;

use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Attribute\Backend\VaultBackend;

/** #[Vault]'s part on Doctrine (plan "omnibase agnostic", step F2b; moves to omnibridge/doctrine at F3). */
final class DoctrineVaultBackend implements VaultBackend
{
    public function __construct(private readonly DoctrineUnitOfWork $unitOfWork)
    {
    }

    public function scheduleUpdate(object $entity): void
    {
        $this->unitOfWork->entityManager()->getUnitOfWork()->scheduleForUpdate($entity);
    }
}
