<?php

namespace Base\Console\Command;

use Base\BaseBundle;
use Base\Traits\CacheClearTrait;
use Base\Console\Command;
use Base\Notifier\Notifier;
use Base\Service\Flysystem;
use Base\Service\LocalizerInterface;
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

#[AsCommand(name: 'cache:clear', aliases: [], description: '')]
class CacheClearCommand extends Command
{
    use CacheClearTrait;

    /** @var string */
    protected string $projectDir;
    /** @var string */
    protected string $cacheDir;
    /** @var string */
    public static string $testFile;

    /**
     * @var SymfonyCacheClearCommand
     */
    protected SymfonyCacheClearCommand $cacheClearCommand;

    /**
     * @var Flysystem
     */
    protected Flysystem $flysystem;

    /**
     * @var Notifier
     */
    protected Notifier $notifier;

    /**
     * @var RouterInterface
     */
    protected RouterInterface $router;

    public function __construct(
        LocalizerInterface       $localizer,
        TranslatorInterface      $translator,
        EntityManagerInterface   $entityManager,
        ParameterBagInterface    $parameterBag,
        SymfonyCacheClearCommand $cacheClearCommand,
        Flysystem                $flysystem,
        Notifier                 $notifier,
        RouterInterface          $router,
        string                   $projectDir,
        string                   $cacheDir
    )
    {
        parent::__construct($localizer, $translator, $entityManager, $parameterBag);
        $this->cacheClearCommand = $cacheClearCommand;

        $this->flysystem = $flysystem;
        $this->notifier = $notifier;
        $this->router = $router;

        $this->projectDir = $projectDir;
        $this->cacheDir = $cacheDir;

        self::$testFile = $this->cacheDir . ".txt";
        self::markAsFirstClear(!file_exists(self::$testFile));
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
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> command clears and warms up the application cache for a given environment
and debug mode:

  <info>php %command.full_name% --env=dev</info>
  <info>php %command.full_name% --env=prod --no-debug</info>
EOF
            );
    }

    public function getTestFile(): string { return self::$testFile; }
    public static function markAsFirstClear(bool $first = true) { if($first) file_put_contents(self::$testFile, 0); }
    public static function applicationNotStarted(): bool { return self::getNClears() < 1; }
    public static function isFirstClear(): bool { return self::getNClears() < 2; }
    public static function getNClears(): int { return file_exists(self::$testFile) ? intval(file_get_contents(self::$testFile)) : 0; }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->cacheClearCommand->setApplication($this->getApplication());

        $noExtension = $input->getOption('no-extension') ?? true;
        if (!$noExtension) {
            $this->phpConfigCheck($io);
            $this->diskAndMemoryCheck($io);
            $this->customFeatureWarnings($io);
            $this->checkCache($io);
            $this->checkExtensions($io);
        }

        $noWarmup = $input->getOption('no-warmup');
        $noOptionalWarmers = $input->getOption('no-optional-warmers') || $noWarmup;
        if (!$noOptionalWarmers) {
            $io->write("\n // <info>All</info> cache warmers requested.", true);
        } else {
            $io->write("\n // Optional cache warmers disabled.", true);
        }

        $ret = $this->cacheClearCommand->execute($input, $output);
        self::markAsFirstClear(!file_exists(self::$testFile));
        file_put_contents(self::$testFile, self::getNClears()+1);
        
        if (!$noExtension) {

            $this->doubleCacheClearCheck($io);
            $this->webpackCheck($io);
            $this->generateSymlinks($io);
            $this->technicalSupportCheck($io);
        }

        return $ret;
    }
}
