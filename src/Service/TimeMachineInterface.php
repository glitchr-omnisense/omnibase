<?php

namespace Base\Service;

interface TimeMachineInterface
{
    public function getSnapshot(int $id, int|array $storageNames, $prefix = null, $cycle = -1);
    public function backup(null|string|array $databases, int|array $storageNames = [], $prefix = null, $cycle = -1);
    public function restore(int $id, bool $restoreDatabase, bool $restoreApplication, int|array $storageNames = [], $prefix = null, $cycle = -1);
}