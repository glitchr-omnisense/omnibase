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

/**
 *
 */
#[AsCommand(name: 'timemachine:snapshot', aliases: [], description: '')]
class TimeMachineSnapshotCommand extends Command
{
    /**
     * @var TimeMachineInterface
     */
    protected $timeMachine;

    /**
     * @var FlysystemInterface
     */
    protected $flysystem;

    public function __construct(
        LocalizerInterface     $localizer,
        TranslatorInterface    $translator,
        EntityManagerInterface $entityManager,
        ParameterBagInterface  $parameterBag,
        TimeMachineInterface   $timeMachine,
        FlysystemInterface     $flysystem
    )
    {
        parent::__construct($localizer, $translator, $entityManager, $parameterBag);
        $this->timeMachine = $timeMachine;
        $this->flysystem = $flysystem;
    }

    protected function configure(): void
    {
        $this->addArgument('storages', InputArgument::IS_ARRAY, 'What storages do you want to backup?');
        $this->addOption('cycle', null, InputOption::VALUE_OPTIONAL, 'Which version do you want to get?', null);
        $this->addOption('prefix', null, InputOption::VALUE_OPTIONAL, 'Which prefix do you want to use?', "backup");
        $this->addOption('database', null, InputOption::VALUE_OPTIONAL, 'Which database do you want to backup?', null);
        $this->addOption('userlog', null, InputOption::VALUE_NONE, 'Save user info too?', null);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->timeMachine->setCommandOutput($output);

        $storages = $input->getArgument('storages') ?? [];
        $database = $input->getOption('database') ?? null;
        $prefix   = $input->getOption('prefix') ?? null;
        $userlog  = $input->getOption('userlog') ?? null;
        $cycle    = $input->getOption('cycle') ?? -1;

        if($userlog) {
            $output->section()->writeln("<info>User configuration will be included in the backup</info>");
        }
        
        $output->section()->writeln("<info>Available database connection(s)</info>:");
        foreach ($this->timeMachine->getDatabaseList() as $connectionName => $connection) {

            $selected = $connectionName == $database ? " <warning><-- selected</warning> " : "";
            $output->section()->writeln(" * <info>" . $connectionName . "</info> (" . get_class($connection) . ")".$selected);
        }

        $output->section()->writeln("");

        $output->section()->writeln("<info>Storage filesystem:</info> ");
        foreach ($this->timeMachine->getStorageList() as $storageName => $storage) {
            if ($this->flysystem->hasStorage($storageName)) {
                $public = $this->flysystem->getPublic("/", $storageName);

                $selected = in_array($storageName, $storages);
                $selected = $selected ? " <warning><-- selected</warning> " : "";

                $remote = $this->flysystem->isRemote($storageName) ? "<magenta>(remote)</magenta> " : "";
                $output->section()->writeln("* [<info>" . $storageName . "</info>] " . $remote . $public . $selected);
            }
        }

        $output->section()->writeln("");

        $prefixStr = $prefix ? "prefixed by \"".$prefix."\"" : "";

        $index = 0;
        foreach ($this->timeMachine->getSnapshots($storages, $prefix, $cycle) as $storageName => $snapshot) {
            $output->section()->writeln("<info>Available snapshot(s)</info> ".$prefixStr." <info>in</info> " . $storageName, OutputInterface::VERBOSITY_VERBOSE);

            $public = $this->flysystem->getPublic("/", $storageName);
            if (!$snapshot) {
                $output->section()->writeln("* No snapshot found", OutputInterface::VERBOSITY_VERBOSE);
            }
            foreach ($snapshot as $file) {
                $output->section()->writeln("* [<info>" . $index++ . "</info>] " . $public . $file, OutputInterface::VERBOSITY_VERBOSE);
            }

            $output->section()->writeln("", OutputInterface::VERBOSITY_VERBOSE);
        }

        return Command::SUCCESS;
    }
}
