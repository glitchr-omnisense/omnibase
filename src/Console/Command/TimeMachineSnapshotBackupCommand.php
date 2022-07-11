<?php

namespace Base\Console\Command;

use BackupManager\Filesystems\Destination;
use Base\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'timemachine:snapshot:restore', aliases:[], description:'')]
class TimeMachineSnapshotBackupCommand extends Command
{
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $version   = $input->getOption('version') ?? -1;
        foreach($this->timeMachine->getSnapshots() as $snapshot)
            dump($snapshot);

        $filename = $this->filePrefix."-".(new \DateTime())->format('Ymd')."-".$version;

        $destinations = [];
        foreach ($input->getArgument('destinations') as $name)
            $destinations[] = new Destination($name, $filename);

        $this->timeMachine->restore($id, $version);

        return Command::SUCCESS;
    }
}