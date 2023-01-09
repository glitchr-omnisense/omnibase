<?php

namespace Base\Console\Command;

use Base\Console\Command;
use Base\Notifier\Notifier;
use Base\Service\Flysystem;
use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\CacheClearCommand as SymfonyCacheClearCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name:'cache:clear', aliases:[], description:'')]
class CacheClearCommand extends Command
{
    /**
     * @var Flysystem
     */
    protected $flysystem;

    /**
     * @var Notifier
     */
    protected $notifier;
    
    public function __construct(
        LocaleProviderInterface $localeProvider, TranslatorInterface $translator, EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag, 
        SymfonyCacheClearCommand $cacheClearCommand, Flysystem $flysystem, Notifier $notifier, string $projectDir, string $cacheDir)
    {
        parent::__construct($localeProvider, $translator, $entityManager, $parameterBag);
        $this->cacheClearCommand = $cacheClearCommand;

        $this->flysystem = $flysystem;
        $this->notifier  = $notifier;

        $this->projectDir  = $projectDir;
        $this->cacheDir    = $cacheDir;
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDefinition([
                new InputOption('no-warmup', '', InputOption::VALUE_NONE, 'Do not warm up the cache'),
                new InputOption('no-optional-warmers', '', InputOption::VALUE_NONE, 'Skip optional cache warmers (faster)'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command clears and warms up the application cache for a given environment
and debug mode:

  <info>php %command.full_name% --env=dev</info>
  <info>php %command.full_name% --env=prod --no-debug</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $testFile = $this->cacheDir."/.test";
        $testFileExists = file_exists($testFile);

        $this->cacheClearCommand->setApplication($this->getApplication());
        $ret = $this->cacheClearCommand->execute($input, $output);

        //
        // Check for node_modules directory
        if(!is_dir($this->projectDir."/var/modules") && !is_dir($this->projectDir."/node_modules")) {

            $io->error(
                'Node package manager directory `'.$this->projectDir."/node_modules".'` is missing. '.PHP_EOL.
                'Run `npm install` to setup your dependencies !'
            );
        }

        //
        // Run second cache clear command
        file_put_contents($testFile, "Hello World !");
        if(!$testFileExists)
            $io->warning('Cache requires to run a second `cache:clear` to account for the custom base bundle features.');

        //
        // Generate flysystem public symlink

        $storageNames = $this->flysystem->getStorageNames(false);
        if($storageNames)
            $io->note("Flysystem symlink(s) got generated in public directory.");

        foreach($storageNames as $storageName) {

            if(!$this->flysystem->hasStorage($storageName.".public"))
                continue;

            $realPath = str_rstrip($this->flysystem->prefixPath("", $storageName), "/");

            $publicPath = $this->flysystem->getPublicRoot($storageName.".public");
            $publicPath = str_rstrip($publicPath, "/");
            if($realPath == $publicPath)
                continue;

            if(is_link($publicPath) || file_exists($publicPath)) {

                if(is_link($publicPath)) unlink($publicPath);
                else if(is_emptydir($publicPath)) rmdir($publicPath);
                else exit("Public path \"$publicPath\" already exists but it is not a symlink\n");
            }

            symlink($realPath, $publicPath);
        }

        //
        // Technical contact and language
        $technicalRecipient = $this->notifier->getTechnicalRecipient();
        if(is_stringeable($technicalRecipient))
            $io->note("Technical recipient configured: ".$technicalRecipient);

        //
        // Disk space and memory checks
        $freeSpace = disk_free_space(".");
        $diskSpace = disk_total_space(".");
        $remainingSpace = $diskSpace - $freeSpace;
        $percentSpace = round(100*$remainingSpace/$diskSpace, 2);
        
        if($percentSpace > 95) $fn = "warning";
        else if($percentSpace > 75) $fn = "note";
        else $fn = "info";

        $memoryLimit = str2dec(ini_get("memory_limit"));
        $memoryLimitStr = $memoryLimit > 0 ? 'PHP Memory limit: ' . byte2str($memoryLimit, array_slice(BINARY_PREFIX, 0, 3)) : "";
        $io->$fn(
            'Disk space information: '. byte2str($freeSpace, BINARY_PREFIX) . ' / ' . byte2str($diskSpace, BINARY_PREFIX) . " available (".$percentSpace." % used)".PHP_EOL.
            $memoryLimitStr
        );

        if($memoryLimit > 0 && $memoryLimit < str2dec("512M"))
            $io->warning('Memory limit is very low.. Consider to increase it');

        return $ret;
    }
}
