<?php

namespace Base\Bridge\Doctrine\Behaviour;

use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Attribute\Backend\Backends;
use Base\Database\Attribute\Backend\CacheBackend;
use Base\Database\Attribute\Backend\SlugifyBackend;
use Base\Database\Attribute\Backend\VaultBackend;

/**
 * The behaviours' parts on Doctrine, registered for DoctrineUnitOfWork (plan
 * "omnibase agnostic", step F2b): AttributeSubscriber, the Doctrine relay that
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
    ];

    public static function register(): void
    {
        foreach (self::BACKENDS as $behaviour => $backend) {
            Backends::register(DoctrineUnitOfWork::class, $behaviour, $backend);
        }
    }
}
