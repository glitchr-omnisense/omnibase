<?php

namespace Base\Console\Command;

use BackupManager\Filesystems\Destination;
use Base\Console\Command;
use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TimeMachine;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name:'timemachine:snapshot', aliases:[], description:'')]
class TimeMachineSnapshotCommand extends Command
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

    protected function configure(): void
    {
        $this->addArgument('storages', InputArgument::IS_ARRAY, 'What storages do you want to backup?');
        $this->addOption  ('cycle', null, InputOption::VALUE_OPTIONAL, 'Which version do you want to get?', 'null');
        $this->addOption  ('id', null, InputOption::VALUE_OPTIONAL, 'Which ID do you want to process?', 'null');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $storages = $input->getArgument('storages') ?? [];
        $cycle   = $input->getOption('cycle') ?? -1;

        foreach($this->timeMachine->getSnapshots($storages, $cycle) as $snapshot)
            dump($snapshot);

        return Command::SUCCESS;
    }
}