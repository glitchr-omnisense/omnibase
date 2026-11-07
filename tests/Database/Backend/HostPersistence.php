<?php

namespace Tests\Base\Database\Backend;

use Base\Database\Repository\Repositories;
use Omnibridge\Persistence\Backend;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The persistence the host application chose (plan "omnibase agnostic", step F7f): its name, its unit of work and its
 * repositories, as the container hands them to the core's services - Doctrine's in the harness and in a site on
 * doctrine, omnibridge/eloquent's in a site on eloquent (base.persistence). A test writing what the code under test
 * reads writes it here, so that the entities it hands that code are ones the same persistence manages; in a host on
 * Doctrine nothing changes (omnibridge/doctrine's unit of work over the same entity manager).
 *
 * A test of Doctrine itself (its container, its queries, its event manager) asks requireDoctrineHost(): it is skipped,
 * saying so, where the host is on another persistence.
 */
trait HostPersistence
{
    /** The name of the persistence the host chose: doctrine, eloquent. */
    protected function hostPersistence(): string
    {
        return static::getContainer()->get(Backend::class)->name();
    }

    protected function hostUnitOfWork(): UnitOfWork
    {
        return static::getContainer()->get(UnitOfWork::class);
    }

    protected function hostRepositories(): Repositories
    {
        return static::getContainer()->get(Repositories::class);
    }

    /** The entity read through the host's persistence (its identity map first). */
    protected function hostFind(string $class, mixed $id): ?object
    {
        return $this->hostRepositories()->of($class)->find($id);
    }

    /** The host's identity map emptied: the next reads make new objects (no such call in the UnitOfWork contract). */
    protected function hostClear(): void
    {
        $unitOfWork = $this->hostUnitOfWork();
        if (method_exists($unitOfWork, 'entityManager')) {
            $unitOfWork->entityManager()->clear();
        } elseif (method_exists($unitOfWork, 'clear')) {
            $unitOfWork->clear();
        }
    }

    /** The host's database connection, under whichever persistence: the PDO Doctrine and Eloquent share. */
    protected function hostPdo(): \PDO
    {
        $unitOfWork = $this->hostUnitOfWork();
        if (method_exists($unitOfWork, 'entityManager')) {
            return $unitOfWork->entityManager()->getConnection()->getNativeConnection();
        }

        return $unitOfWork->store()->connection()->getPdo();
    }

    /** Skipped, saying why, unless the host is on Doctrine: for what tests Doctrine itself. */
    protected function requireDoctrineHost(string $what): void
    {
        if ('doctrine' !== ($persistence = $this->hostPersistence())) {
            self::markTestSkipped(sprintf('%s is Doctrine\'s; this host is on %s (base.persistence): omnibridge/%2$s\'s suite tests its own.', $what, $persistence));
        }
    }
}
