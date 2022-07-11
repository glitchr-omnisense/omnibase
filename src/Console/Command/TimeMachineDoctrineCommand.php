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
        $version   = $input->getOption('version') ?? -1;
        foreach($this->timeMachine->getSnapshots() as $snapshot)
            dump($snapshot);

        // $filename = $this->filePrefix."-".(new \DateTime())->format('Ymd')."-".$version;

        // $destinations = [];
        // foreach ($input->getArgument('destinations') as $name)
        //     $destinations[] = new Destination($name, $filename);

        return Command::SUCCESS;
    }
}