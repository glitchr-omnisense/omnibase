<?php

namespace Base\Console\Command;

use BackupManager\Filesystems\Destination;
use Base\Console\Command;
use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\FlysystemInterface;
use Base\Service\TimeMachineInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'timemachine:storage', aliases:[], description:'')]
class TimeMachineStorageCommand extends Command
{
    /**
     * @var TimeMachineInterface
     */
    protected $timeMachine;

    public function __construct(
        LocaleProviderInterface $localeProvider, TranslatorInterface $translator, EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag,
        TimeMachineInterface $timeMachine, FlysystemInterface $flysystem)
    {
        parent::__construct($localeProvider, $translator, $entityManager, $parameterBag);
        $this->timeMachine = $timeMachine;
        $this->flysystem   = $flysystem;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->section()->writeln("Available storage place(s):");
        
        foreach($this->timeMachine->getStorageList() as $storageName => $storage)
            $output->section()->writeln(" * <info>" . $storageName . "</info> (". get_class($storage).")");

        return Command::SUCCESS;
    }
}