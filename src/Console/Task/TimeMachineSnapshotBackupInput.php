<?php

namespace Base\Console\Task;

/** timemachine:snapshot:backup's options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class TimeMachineSnapshotBackupInput extends TimeMachineSnapshotInput
{
    /**
     * @param bool $batch   no confirmation asked
     * @param bool $userlog the users' configuration in the backup too
     */
    public function __construct(array $storages = [], ?string $database = null, ?string $prefix = null, int $cycle = -1, public bool $batch = false, public bool $userlog = false)
    {
        parent::__construct($storages, $database, $prefix, $cycle);
    }
}
