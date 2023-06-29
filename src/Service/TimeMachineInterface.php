<?php

namespace Base\Service;

/**
 *
 */
interface TimeMachineInterface
{
    public function findOneBy(int $id, int|array $storageNames, ?string $prefix = null, int $cycle = -1);
    public function findBy(int|array $storageNames, ?string $prefix = null, int $cycle = -1);
    public function findByCycle(int|array $storageNames, ?string $prefix = null, int $cycle = -1);

    public function backup(null|string|array $databases, int|array $storageNames = [], bool $userInfo = false, ?string $prefix = null, int $cycle = -1);

    public function restore(int $id, bool $restoreDatabase, bool $restoreApplication, int|array $storageNames = [], ?string $prefix = null, int $cycle = -1);
}
