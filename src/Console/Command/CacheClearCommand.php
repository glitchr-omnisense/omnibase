<?php

namespace Base\Console\Command;

use Base\Traits\CacheClearTrait;
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

use Base\Routing\RouterInterface;

#[AsCommand(name:'cache:clear', aliases:[], description:'')]
class CacheClearCommand extends Command
{
    use CacheClearTrait;

    /** @var string */
    protected string $projectDir;
    /** @var string */
    protected string $cacheDir;
    /** @var string */
    protected string $testFile;
    /** @var string */
    protected string $testFileExists;

    /**
     * @var SymfonyCacheClearCommand
     */
    protected $cacheClearCommand;

    /**
     * @var Flysystem
     */
    protected $flysystem;
    
    /**
     * @var Notifier
     */
    protected $notifier;
    
    /**
     * @var Router
     */
    protected $router;
    
    public function __construct(
        LocaleProviderInterface $localeProvider, TranslatorInterface $translator, EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag, 
        SymfonyCacheClearCommand $cacheClearCommand, Flysystem $flysystem, Notifier $notifier, RouterInterface $router, string $projectDir, string $cacheDir)
    {
        parent::__construct($localeProvider, $translator, $entityManager, $parameterBag);
        $this->cacheClearCommand = $cacheClearCommand;

        $this->flysystem = $flysystem;
        $this->notifier  = $notifier;
        $this->router    = $router;

        $this->projectDir  = $projectDir;
        $this->cacheDir    = $cacheDir;
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDefinition([
                new InputOption('no-extension', '', InputOption::VALUE_NONE, 'Skip base extension'),
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
        $noExtension = $input->getOption('no-extension') ?? true;
        if(!$noExtension) {

            $this->phpConfigCheck($io);
            $this->diskAndMemoryCheck($io);
            $this->customFeatureWarnings($io);
            $this->checkCache($io);
            
            $this->testFile = $this->cacheDir."/.test";
            $this->testFileExists = file_exists($this->testFile);
        }

        $this->cacheClearCommand->setApplication($this->getApplication());
        $ret = $this->cacheClearCommand->execute($input, $output);

        if(!$noExtension) {

            $this->webpackCheck($io);            
            $this->doubleCacheClearCheck($io);
            $this->generateSymlinks($io);
            $this->technicalSupportCheck($io);
        }

        return $ret;
    }
}
