<?php

namespace Base;

if(!isset($_SERVER["APP_TIMER"])) {
    $_SERVER["APP_TIMER"] = microtime(true);
}

use App\Entity\User;
use Base\Database\Type\UTCDateTimeType;
use Base\DependencyInjection\Compiler\Pass\AnnotationPass;
use Base\DependencyInjection\Compiler\Pass\TradingMarketPass;
use Base\DependencyInjection\Compiler\Pass\EntityExtensionPass;
use Base\DependencyInjection\Compiler\Pass\IconProviderPass;
use Base\DependencyInjection\Compiler\Pass\ObfuscatorCompressionPass;
use Base\DependencyInjection\Compiler\Pass\SharerPass;
use Base\DependencyInjection\Compiler\Pass\TagRendererPass;
use Base\DependencyInjection\Compiler\Pass\WorkflowPass;
use Doctrine\DBAL\Types\Type;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

use Base\Bundle\AbstractBaseBundle;
use Base\Console\Command\CacheClearCommand;
use Base\DependencyInjection\Compiler\Pass\DoctrineEnumSubscriberPass;
use Base\DependencyInjection\Compiler\Pass\DoctrineConfigurationPass;
use Base\DependencyInjection\Compiler\Pass\EasyAdminCrudPass;
use Base\Traits\SingletonTrait;

/**
 *
 */
class BaseBundle extends AbstractBaseBundle
{
    public const VERSION = '1.0.0';
    public const USE_CACHE = true;

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
            
            self::getAllClasses($this->getBundleDir() . "/src/Database/Annotation");
            self::getAllClasses($this->getBundleDir() . "/src/Annotations/Annotation");
            
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

        if ($this->container->getParameter("base.database.use_custom")) {
            $this->doctrineReadiness = $this->boot_Doctrine();
        }

        CacheClearCommand::$testFile ??= $this->getCacheDir().".txt";
        $this->boot = true;
    }

    public function boot_VarDumper(): bool
    {
        if (!class_exists(\Symfony\Component\VarDumper\VarDumper::class)) return false;
        if (is_cli()) return false;

        \Symfony\Component\VarDumper\VarDumper::setHandler(function ($var)
        {
            static $startTime = null;
            if ($startTime === null) {
                $startTime = microtime(true);
            }

            (new \Base\Resources\Dumper())->dump(
                (new \Symfony\Component\VarDumper\Cloner\VarCloner())->cloneVar($var)
            );
        });

        return true;
    }

    public function boot_Doctrine(): bool
    {
        // Start session here to access client information
        $timezone = null;
        if (method_exists(User::class, "getCookie")) $timezone = User::getCookie("timezone");
        if (!in_array($timezone, timezone_identifiers_list())) $timezone = "UTC";

        // Set default time to UTC everywhere
        date_default_timezone_set($timezone);
        Type::overrideType('date', UTCDateTimeType::class);
        Type::overrideType('datetime', UTCDateTimeType::class);
        Type::overrideType('datetimetz', UTCDateTimeType::class);

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

        return true;
    }

    public function isBuilt() { return $this->container !== null; }
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
	    $this->container = $container;

        if (!self::$cache) {
            $this->warmUp();
        }

        $container->addCompilerPass(new AnnotationPass());
        $container->addCompilerPass(new DoctrineEnumSubscriberPass());
        $container->addCompilerPass(new DoctrineConfigurationPass());
        $container->addCompilerPass(new EasyAdminCrudPass());
        $container->addCompilerPass(new IconProviderPass());
        $container->addCompilerPass(new EntityExtensionPass());
        $container->addCompilerPass(new SharerPass());
        $container->addCompilerPass(new TradingMarketPass());
        $container->addCompilerPass(new TagRendererPass());
        $container->addCompilerPass(new ObfuscatorCompressionPass());
        $container->addCompilerPass(new WorkflowPass());

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
