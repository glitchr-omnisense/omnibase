<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Service\Storages;
use Base\Service\TimeMachineInterface;

/**
 * timemachine:snapshot (plan "omnibase agnostic", step F5e, slice 3): the database connections, the storages and
 * the snapshots each storage holds; backup() and restore() list them first, then back up or restore - each step
 * confirmed by the host's question ($confirm: the question, its default answer).
 */
final class TimeMachineSnapshot
{
    public function __construct(private readonly TimeMachineInterface $timeMachine, private readonly Storages $flysystem)
    {
    }

    public function run(TimeMachineSnapshotInput $input, ?\Closure $sink = null): Report
    {
        return $this->list($input, new Report($sink));
    }

    /** @param \Closure(string, bool): bool $confirm */
    public function backup(TimeMachineSnapshotBackupInput $input, \Closure $confirm, ?\Closure $sink = null): Report
    {
        $report = $this->list($input, new Report($sink));
        if (!$input->storages) {
            $report->line('Please select a storage. aborted.');

            return $report->fail();
        }

        if ($input->userlog) {
            $report->line("<info>User configuration will be included in the backup</info>\n");
        } else {
            $report->line("<warning>User configuration will not be included in the backup</warning> (use `--userlog` option to include it)\n");
        }

        if ($input->batch) {
            $report->line("<warning>Batch mode enabled..</warning>\n");
        } elseif (!$confirm('You are about to backup this application and its database. Do you wish to continue [Y/n] ? ', false)) {
            return $report;
        }

        if ($this->timeMachine->backup($input->database, $input->storages, $input->userlog, $input->prefix, $input->cycle)) {
            return $report->line("<info>Backup completed successfully.</info>\n");
        }

        $report->line("<error>Backup failed.</error>\n");

        return $report->fail();
    }

    /** @param \Closure(string, bool): bool $confirm */
    public function restore(TimeMachineSnapshotRestoreInput $input, \Closure $confirm, ?\Closure $sink = null): Report
    {
        $report = $this->list($input, new Report($sink));
        if (!$input->storages) {
            return $report->fail();
        }

        if ($input->id < 0 && !$confirm('You are about to restore the latest version. Do you wish to continue ? [y/N] ', false)) {
            return $report;
        }
        $restoreApplication = $confirm('Do you wish to restore application from tarball ? [y/N] ', false);
        $restoreDatabase = $confirm('Do you wish to restore database from tarball ? [y/N] ', false);

        // The restoration's answer is a success (true), not a status: the former command returned it, so a restored
        // snapshot ended with the status 1.
        return $this->timeMachine->restore($input->id, $restoreDatabase, $restoreApplication, $input->storages, $input->prefix, $input->cycle) ? $report : $report->fail();
    }

    private function list(TimeMachineSnapshotInput $input, Report $report): Report
    {
        $report->line('<info>Available database connection(s)</info>:');
        foreach ($this->timeMachine->getDatabaseList() as $connectionName => $connection) {
            $selected = $connectionName == $input->database ? ' <warning><-- selected</warning> ' : '';
            $report->line('* <info>'.$connectionName.'</info> ('.$connection::class.')'.$selected);
        }

        $report->line('');
        $report->line('<info>Storage filesystem:</info> ');
        foreach ($this->timeMachine->getStorageList() as $storageName => $storage) {
            if ($this->flysystem->hasStorage($storageName)) {
                $selected = \in_array($storageName, $input->storages) ? ' <warning><-- selected</warning> ' : '';
                $remote = $this->flysystem->isRemote($storageName) ? '<magenta>(remote)</magenta> ' : '';
                $report->line('* [<info>'.$storageName.'</info>] '.$remote.$this->flysystem->getPublic('/', $storageName).$selected);
            }
        }

        $report->line('');

        $prefixStr = $input->prefix ? 'prefixed by "'.$input->prefix.'"' : '';
        $index = 0;
        $snapshotsByCycle = $this->timeMachine->findByCycle($input->storages, $input->prefix, $input->cycle);
        if (!$snapshotsByCycle) {
            $report->line('* No snapshot '.$prefixStr.' found', Report::VERBOSE);
        }

        foreach ($snapshotsByCycle as $storageName => $snapshots) {
            $public = $this->flysystem->getPublic('/', $storageName);
            $report->line('<info>Available snapshot(s)</info> '.$prefixStr.' <info>in</info> '.$storageName, Report::VERBOSE);
            if (0 == \count($snapshots)) {
                $report->line('<warning>[No previous history found]</warning>', Report::VERBOSE);
            }

            foreach ($snapshots as $date => $snapshot) {
                $date = \DateTime::createFromFormat('Ymd', (string) $date);
                if ($date) {
                    $report->line('<warning>['.date('D, M j, Y', $date->getTimestamp()).']</warning>', Report::VERBOSE);
                }
                foreach ($snapshot as $file) {
                    $report->line('* [<info>'.$index++.'</info>] '.$public.$file, Report::VERBOSE);
                }
            }

            $report->line('', Report::VERBOSE);
        }

        return $report;
    }
}
