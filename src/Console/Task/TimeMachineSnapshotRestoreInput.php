<?php

namespace Base\Console\Task;

/** timemachine:snapshot:restore's options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class TimeMachineSnapshotRestoreInput extends TimeMachineSnapshotInput
{
    /** @param int $id the snapshot restored (-1: the latest) */
    public function __construct(array $storages = [], ?string $database = null, ?string $prefix = null, int $cycle = -1, public int $id = -1)
    {
        parent::__construct($storages, $database, $prefix, $cycle);
    }
}
