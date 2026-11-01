<?php

namespace Base\Service;

/**
 * The backups (docs/30-operations/backups.md). Its implementation, Base\Service\TimeMachine,
 * extends glitchr/backup-manager's Manager, a suggestion since 4.0: this interface loads
 * without it, so the configuration reads DEFAULT_EXCLUDES here.
 */
interface TimeMachineInterface
{
    /**
     * Paths left out of the application tarball. Defaults to dependency and
     * build output that `composer install` / `yarn` / a cache warmup rebuild
     * from scratch - keeping them would multiply the snapshot size (and the
     * free space it needs) for no restorable data.
     */
    public const DEFAULT_EXCLUDES = [
        "./vendor",
        "./node_modules",
        "./var/cache",
        "./var/log",
        "./var/coverage",
        "./var/phpunit",
        "./.git",
    ];

    public function findOneBy(int $id, int|array $storageNames, ?string $prefix = null, int $cycle = -1);
    public function findBy(int|array $storageNames, ?string $prefix = null, int $cycle = -1);
    public function findByCycle(int|array $storageNames, ?string $prefix = null, int $cycle = -1);

    public function backup(null|string|array $databases, int|array $storageNames = [], bool $userInfo = false, ?string $prefix = null, int $cycle = -1);

    public function restore(int $id, bool $restoreDatabase, bool $restoreApplication, int|array $storageNames = [], ?string $prefix = null, int $cycle = -1);
}
