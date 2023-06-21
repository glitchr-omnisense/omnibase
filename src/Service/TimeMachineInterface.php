<?php

namespace Base\Service;

/**
 *
 */
interface TimeMachineInterface
{
    public function getSnapshots(int|array $storageNames, ?string $prefix = null, int $cycle = -1);
    public function getSnapshot(int $id, int|array $storageNames, ?string $prefix = null, int $cycle = -1);

    public function backup(null|string|array $databases, int|array $storageNames = [], bool $userInfo = false, ?string $prefix = null, int $cycle = -1);

    public function restore(int $id, bool $restoreDatabase, bool $restoreApplication, int|array $storageNames = [], ?string $prefix = null, int $cycle = -1);
}
