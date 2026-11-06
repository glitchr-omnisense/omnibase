<?php

namespace Base\Console\Task;

/** timemachine:snapshot's arguments and options, and those of its backup and restore (plan "omnibase agnostic", step F5e, slice 3). */
readonly class TimeMachineSnapshotInput
{
    /**
     * @param list<string> $storages the storages looked into (written to, read from)
     * @param ?string      $database the database connection backed up
     * @param ?string      $prefix   the snapshots' prefix (Symfony: the environment's name)
     * @param int          $cycle    the cycle (-1: the current one)
     */
    public function __construct(public array $storages = [], public ?string $database = null, public ?string $prefix = null, public int $cycle = -1)
    {
    }
}
