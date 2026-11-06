<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Service\TimeMachineInterface;

/** timemachine:storage (plan "omnibase agnostic", step F5e, slice 3): the storages a snapshot can be written to. */
final class TimeMachineStorage
{
    public function __construct(private readonly TimeMachineInterface $timeMachine)
    {
    }

    public function run(?\Closure $sink = null): Report
    {
        $report = (new Report($sink))->line('Available storage place(s):');
        foreach ($this->timeMachine->getStorageList() as $storageName => $storage) {
            $report->line(' * <info>'.$storageName.'</info> ('.$storage::class.')');
        }

        return $report;
    }
}
