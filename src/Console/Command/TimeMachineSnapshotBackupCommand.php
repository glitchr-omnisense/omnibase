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
        $cycle   = $input->getOption('cycle') ?? -1;
        foreach($this->timeMachine->getSnapshots() as $snapshot)
            dump($snapshot);

        $id   = $input->getOption('id');
        if(!$id) throw new \Exception("Please select an ID.");

        $filename = $this->filePrefix."-".(new \DateTime())->format('Ymd')."-".$cycle;

        $destinations = [];
        foreach ($input->getArgument('destinations') as $name)
            $destinations[] = new Destination($name, $filename);

        $this->timeMachine->restore($id, $cycle);

        return Command::SUCCESS;
    }
}