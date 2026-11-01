<?php

namespace Base\Bridge\Doctrine\Behaviour;

use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Attribute\Backend\Backends;
use Base\Bridge\Doctrine\DoctrineShapes;
use Base\Database\Attribute\Backend\CacheBackend;
use Base\Database\Attribute\Backend\OrderColumnBackend;
use Base\Database\Attribute\Backend\SlugifyBackend;
use Base\Database\Attribute\Backend\VaultBackend;
use Base\Database\Mapping\EntityShapes;
use Base\Bridge\Doctrine\DoctrineRepositories;
use Base\Database\Backend\ConditionalUpdates;
use Base\Database\Backend\EntityCache;
use Base\Database\Backend\RowLocks;
use Base\Database\Repository\Repositories;

/**
 * The behaviours' parts on Doctrine, the entities' shapes (step F2c), the repositories and what the
 * core's services ask beyond the unit of work's contract (step F2d), registered for
 * DoctrineUnitOfWork (plan "omnibase agnostic", step F2b): AttributeSubscriber, the Doctrine relay that
 * dispatches the neutral lifecycle hooks, registers them when it is made, under
 * Symfony and in Base\Bridge\Doctrine\Mapping alike. Moves to omnibridge/doctrine
 * at step F3, with the three implementations.
 */
final class DoctrineBehaviours
{
    public const BACKENDS = [
        VaultBackend::class => DoctrineVaultBackend::class,
        SlugifyBackend::class => DoctrineSlugifyBackend::class,
        CacheBackend::class => DoctrineCacheBackend::class,
        OrderColumnBackend::class => DoctrineOrderColumnBackend::class,
        EntityShapes::class => DoctrineShapes::class,
        // Step F2d: what the core's services ask of a unit of work beyond its contract.
        Repositories::class => DoctrineRepositories::class,
        RowLocks::class => DoctrineRowLocks::class,
        ConditionalUpdates::class => DoctrineConditionalUpdates::class,
        EntityCache::class => DoctrineEntityCache::class,
    ];

    private static bool $registered = false;

    public static function register(): void
    {
        self::$registered = true;
        foreach (self::BACKENDS as $behaviour => $backend) {
            Backends::register(DoctrineUnitOfWork::class, $behaviour, $backend);
        }
    }

    /** Registers them unless done: a DoctrineUnitOfWork made before the relay (a service's, at the top of a request) finds them. */
    public static function ensureRegistered(): void
    {
        if (!self::$registered) {
            self::register();
        }
    }
}
