<?php

namespace Base\Bridge\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\Proxy;

/**
 * doctrine/persistence's ManagerRegistry over one entity manager and its
 * connection, both named "default": what omnibase's repositories
 * (Base\Database\Repository\ServiceEntityRepository) and the
 * ClassMetadataManipulator are built from, where DoctrineBundle's `doctrine`
 * service is not there (Base\Bridge\Doctrine\Mapping).
 */
final class Registry extends AbstractManagerRegistry
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct('ORM', ['default' => 'connection'], ['default' => 'entity_manager'], 'default', 'default', Proxy::class);
    }

    protected function getService(string $name): object
    {
        return $name === 'connection' ? $this->entityManager->getConnection() : $this->entityManager;
    }

    protected function resetService(string $name): void
    {
    }
}
