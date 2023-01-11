<?php

namespace Base\Console\Command;

use BackupManager\Filesystems\Destination;
use Base\Console\Command;
use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TimeMachine;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'timemachine:doctrine', aliases:[], description:'')]
class TimeMachineDoctrineCommand extends Command
{
    /**
     * @var TimeMachine
     */
    protected $timeMachine;

    public function __construct(
        LocaleProviderInterface $localeProvider, TranslatorInterface $translator, EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag,
        TimeMachine $timeMachine)
    {
        parent::__construct($localeProvider, $translator, $entityManager, $parameterBag);
        $this->timeMachine = $timeMachine;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->section()->writeln("Available database connection(s):");
        
        foreach($this->timeMachine->getStorageList() as $storageName => $storage)
            $output->section()->writeln(" * <info>" . $databaseName . "</info> (". get_class($database).")");

        return Command::SUCCESS;
    }
}