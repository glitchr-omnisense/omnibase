<?php

namespace Base\Console\Command;

use BackupManager\Filesystems\Destination;
use Base\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 *
 */
#[AsCommand(name: 'timemachine:snapshot:backup', aliases: [], description: '')]
class TimeMachineSnapshotBackupCommand extends TimeMachineSnapshotCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Which version do you want to get?', null);
        $this->addOption('batch', null, InputOption::VALUE_NONE, 'Do you run batch command ?', null);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $storages  = $input->getArgument('storages') ?? [];
        $database  = $input->getOption('database')   ?? null;
        $batchMode = $input->getOption('batch')      ?? false;
        $userInfo  = $input->getOption('userlog')    ?? false;
        $prefix    = $input->getOption('prefix')     ?? null;
        $cycle     = $input->getOption('cycle')      ?? -1;

        if (!$storages) {
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('You are about to backup this application and its database. Do you wish to continue [Y/n] ? ', false);
        if ($batchMode) $output?->section()->writeln("<warning>Batch mode detected..</warning>\n");
        if(!$batchMode && !$helper->ask($input, $output, $question))
            return Command::SUCCESS;

        return $this->timeMachine->backup($database, $storages, $userInfo, $prefix, $cycle);
    }
}
