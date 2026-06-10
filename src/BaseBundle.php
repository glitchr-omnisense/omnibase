<?php

namespace Base;

if(!isset($_SERVER["APP_TIMER"])) {
    $_SERVER["APP_TIMER"] = microtime(true);
}

use App\Entity\User;
use Base\Database\Type\DateTimeTypeUTC as DateTimeType;
use Base\Database\Type\ArrayType;
use Doctrine\DBAL\Types\Type;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

use Base\Bundle\AbstractBaseBundle;
use Base\Console\Command\CacheClearCommand;
use Base\DependencyInjection\Dumper\CliDumper;
use Base\DependencyInjection\Dumper\HtmlDumper;

class BaseBundle extends AbstractBaseBundle
{
    public const VERSION = '1.0.0';

    public function __construct()
    {
        if (!$this->hasInstance()) {
            self::$_instance = $this;
        }
    }

    /**
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->container->getParameter('kernel.cache_dir');
    }

    /**
     * @return string
     */
    public function getPublicDir(): string 
    {
        return $this->getProjectDir() . "/public";
    }

    /**
     * @return string
     */
    public function getSourceDir(): string
    {
        return $this->getProjectDir() . "/src";
    }

    /**
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->container->getParameter('kernel.environment');
    }

    protected bool $boot = false;

    /**
     * @return bool
     */
    public function isBooted()
    {
        return $this->boot;
    }

    protected bool $doctrineReadiness = false;
    public function isDoctrineReady(): bool
    {
        return $this->doctrineReadiness;
    }

    //
    // Some subscribers are not called when modifying codes.
    // The purpose of this broken cache feature is to prevent running without these subscribers
    protected bool $invalidCache = true; // Turned off in subscribers if everything fine.

    /**
     * @return bool
     */
    public function isInvalid(): bool
    {
        return $this->invalidCache;
    }

    public function markCacheAsValid(): void
    {
        $this->invalidCache = false;
    }

    public static function getInstance(bool $instanciateIfNotFound = true): ?self
    {
        return parent::getInstance($instanciateIfNotFound);
    }
    
    public function warmUp()
    {
        $needsWarmup = !file_exists($this->getCacheDir() . "/pools/base/bundle.php");
        self::$cache               = new PhpArrayAdapter($this->getCacheDir() . "/pools/base/bundle.php", new FilesystemAdapter("", 0, $this->getCacheDir() . "/pools/base/fallback"));
        self::$files               = self::$files               ?? self::$cache->getItem('base.files')->get() ?? [];
        self::$classes             = self::$classes             ?? self::$cache->getItem('base.classes')->get() ?? [];
        self::$aliasList           = self::$aliasList           ?? self::$cache->getItem('base.alias_list')->get() ?? [];
        self::$aliasRepositoryList = self::$aliasRepositoryList ?? self::$cache->getItem('base.alias_repository_list')->get() ?? [];

        foreach (self::$aliasList as $class => $alias) {
            class_alias($class, $alias);
        }
        foreach (self::$aliasRepositoryList as $class => $alias) {
            class_alias($class, $alias);
        }

        if ($needsWarmup) {

            foreach(array_reverse($this->getBundles()) as $baseBundle)
            {
                if($baseBundle == BaseBundle::class) continue;

                $classRefl = new \ReflectionClass($baseBundle);
                $classPath = dirname($classRefl->getFileName());

                $baseNamespace = dirname_namespace($baseBundle);
                foreach(self::getDirectories($classPath, 1) as $namepath)
                {
                    $namespace = basename($namepath);

                    $baseClass = $baseNamespace . "\\". $namespace;
                    $baseClassArray = explode("\\", $baseClass);
                    array_swap($baseClassArray, 1, 2);
                    
                    $baseClassSwap = implode("\\", $baseClassArray);
                    $this->setMapping($classPath . "/".$namespace     , $baseClass     , $baseClassSwap);
                }
            }

            $this->setMapping($this->getBundleDir() . "/src/Entity"    , "Base\Entity"    , "App\Entity");
            $this->setMapping($this->getBundleDir() . "/src/Repository", "Base\Repository", "App\Repository");
            $this->setMapping($this->getBundleDir() . "/src/Enum"      , "Base\Enum"      , "App\Enum");

            $this->setMapping($this->getBundleDir() . "/src/Tests"     , "Base\Tests"     , "App\Tests");
            $this->setMapping($this->getBundleDir() . "/src/Enum"      , "Base\Enum"      , "App\Enum");
            $this->setMapping($this->getBundleDir() . "/src/Notifier"  , "Base\Notifier"  , "App\Notifier");
            $this->setMapping($this->getBundleDir() . "/src/Form"      , "Base\Form"      , "App\Form");
            $this->setMapping($this->getBundleDir() . "/src/Entity"    , "Base\Entity"    , "App\Entity");
            $this->setMapping($this->getBundleDir() . "/src/Repository", "Base\Repository", "App\Repository");
            
            self::getAllClasses($this->getBundleDir() . "/src/Database/Attribute");
            self::getAllClasses($this->getBundleDir() . "/src/Attributes/Attribute");
            
            self::getAllClasses($this->getBundleDir() . "/src/Enum");
            self::getAllClasses($this->getProjectDir() . "/src/Enum");
            
            self::$cache->warmUp([
                "base.files" => self::$files ?? [],
                "base.classes" => self::$classes ?? [],
                "base.alias_list" => self::$aliasList ?? [],
                "base.alias_repository_list" => self::$aliasRepositoryList ?? []
            ]);
        }
    }

    public function boot(): void
    {
        if (!extension_loaded('imagick')) {
           throw new EnvNotFoundException('Application requires `imagick`, but it is not enabled.');
        }
        
        if (!extension_loaded('igbinary')) {
           throw new EnvNotFoundException('Application requires `igbinary`, but it is not enabled.');
        }

        if (!self::$cache) {
            $this->warmUp();
        }

        if (class_exists(\Symfony\Component\VarDumper\VarDumper::class)) {

            $htmlDumper = new HtmlDumper();
            $cliDumper = new CliDumper();

            \Symfony\Component\VarDumper\VarDumper::setHandler(function ($var) use ($htmlDumper, $cliDumper) {

                static $startTime = null;
                if ($startTime === null) {
                    $startTime = microtime(true);
                }

                if(is_cli()) {
                    $dumper = $cliDumper;
                } else {
                    $dumper = $htmlDumper;
                }

                $dumper->dump(
                    (new \Symfony\Component\VarDumper\Cloner\VarCloner())->cloneVar($var)
                );
            });
        }

        
        if ($this->container->getParameter("base.database.use_custom")) {
                
            // Start session here to access client information
            $timezone = null;
            if (method_exists(User::class, "getCookie")) $timezone = User::getCookie("timezone");
            if (!in_array($timezone, timezone_identifiers_list())) $timezone = "UTC";

            // Set default time to UTC everywhere
            date_default_timezone_set($timezone);
            Type::overrideType('date', DateTimeType::class);
            Type::overrideType('datetime', DateTimeType::class);
            Type::overrideType('datetimetz', DateTimeType::class);

            // Backward compatibility (see doctrine:array:upgrade)
            if(Type::hasType('array')) Type::overrideType('array', ArrayType::class);
            else Type::addType('array', ArrayType::class);

            $classList = array_merge(
                self::getAllClasses(self::getBundleDir() . "/src/Enum"),
                self::getAllClasses($this->getProjectDir() . "/src/Enum")
            );

            foreach ($classList as $className) {

                if(!Type::hasType($className::getStaticName())) {
                    Type::addType($className::getStaticName(), $className);
                }

                $type = Type::getType($className::getStaticName());
                if($type == $className) {
                    throw new EnvNotFoundException('Doctrine type `'.$className::getStaticName().'` already exists, conflict detected between '. $className." and ". get_class($type));
                }
            }

            $entityManager = $this->container->get('doctrine.orm.entity_manager'); 
            $entityManager->getFilters()->enable("trash_filter");
            $entityManager->getFilters()->enable("vault_filter")->setEnvironment($this->getEnvironment());
        }

        CacheClearCommand::$testFile ??= $this->getCacheDir().".txt";
        $this->boot = true;
    }

    public function isBuilt() { return $this->container !== null; }
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
	    $this->container = $container;

        if (!self::$cache) {
            $this->warmUp();
        }

        /* Register compiler passes */
        $finder = new \Symfony\Component\Finder\Finder();
        $finder->files()
            ->in($this->getBundleDir() . '/src/DependencyInjection/Compiler/Pass')
            ->name('*Pass.php')
            ->notName('AbstractPass.php');

        foreach ($finder as $file) {
            $class = 'Base\\DependencyInjection\\Compiler\\Pass\\' . $file->getBasename('.php');
            if (class_exists($class) && !in_array($class, [__CLASS__])) {
                $priority = method_exists($class, 'getPriority') ? $class::getPriority() : 0;
                $container->addCompilerPass(new $class(), priority: $priority);
            }
        }

        /* Register aliased repositories */
        foreach (self::$aliasRepositoryList as $baseRepository => $aliasedRepository) {
            
            $container->register($baseRepository)
                      ->addTag("doctrine.repository_service")
                      ->addArgument(new Reference('doctrine'));

            if ($aliasedRepository) {
                $container->register($aliasedRepository)
                          ->addTag("doctrine.repository_service")
                          ->addArgument(new Reference('doctrine'));
            }
        }
    }
}
