<?php

namespace Base\Console\Command;

use BackupManager\Filesystems\Destination;
use Base\Console\Command;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TimeMachineInterface;
use Base\Service\FlysystemInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(name:'timemachine:snapshot:restore', aliases:[], description:'')]
class TimeMachineSnapshotRestoreCommand extends TimeMachineSnapshotCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Which version do you want to get?', null);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $storages = $input->getArgument('storages') ?? [];
        $prefix   = $input->getOption('prefix')     ?? null;
        $cycle    = $input->getOption('cycle')      ?? -1;
        $id       = $input->getOption('id')         ?? -1;

        if (!$storages) {
            return Command::FAILED;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('You are about to restore an old version. Do you wish to continue ? [y/N] ', false);
        if (!$helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Do you wish to restore application from tarball ? [y/N] ', false);
        $restoreApplication = $helper->ask($input, $output, $question);

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Do you wish to restore database from tarball ? [y/N] ', false);
        $restoreDatabase = $helper->ask($input, $output, $question);

        return $this->timeMachine->restore($id, $restoreDatabase, $restoreApplication, $storages, $prefix, $cycle);
    }
}
