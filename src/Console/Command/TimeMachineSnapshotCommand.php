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
        $this->addArgument('storage', InputArgument::IS_ARRAY, 'What storages do you want to upload the backup to? Must be array.');
        $this->addOption  ('version', null, InputOption::VALUE_OPTIONAL, 'Which version do you want to get?', 'null');
        $this->addOption  ('id', null, InputOption::VALUE_OPTIONAL, 'Which ID do you want to process?', 'null');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $version   = $input->getOption('version') ?? -1;
        foreach($this->timeMachine->getSnapshots() as $snapshot)
            dump($snapshot);

        $filename = $this->filePrefix."-".(new \DateTime())->format('Ymd')."-".$version;

        $destinations = [];
        foreach ($input->getArgument('destinations') as $name)
            $destinations[] = new Destination($name, $filename);

        $this->timeMachine->list($id, $version);

        return Command::SUCCESS;
    }
}