<?php

namespace Base\Console\Task;

/** trash:purge's options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class TrashPurgeInput
{
    /**
     * @param bool $force destroy them (only reported otherwise: a dry run)
     * @param ?int $limit at most this many in this run
     */
    public function __construct(public bool $force = false, public ?int $limit = null)
    {
    }
}
