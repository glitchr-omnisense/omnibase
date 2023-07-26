<?php

namespace Base;

$_SERVER["APP_TIMER"] = microtime(true);
include_once("Functions.php");

use Base\Database\Function\Rand;
use DoctrineExtensions\Query\Mysql\Field;
use Exception;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql as DqlFunctions;
use App\Entity\User;
use Base\Database\Filter\TrashFilter;
use Base\Database\Filter\VaultFilter;
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
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

use Base\Bundle\AbstractBaseBundle;

/**
 *
 */
class BaseBundle extends AbstractBaseBundle
{
    public const VERSION = '1.0.0';
    public const USE_CACHE = true;

    /**
     * @return string
     */
    public function getProjectDir(): string
    {
        return $this->container->getParameter('kernel.project_dir');
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

    /**
     * @return string
     */
    public function getApplicationLocation(): string 
    { 
        return $this->getProjectDir();
    }

    protected bool $boot = false;

    /**
     * @return bool
     */
    public function hasBooted()
    {
        return $this->boot;
    }

    protected bool $bootDoctrine = false;
    public function hasDoctrine(): bool
    {
        return $this->bootDoctrine;
    }

    protected static bool $firstClear = true;
    public static function markAsFirstClear(bool $first = true) { self::$firstClear = $first; }
    public static function isFirstClear() { return self::$firstClear; }

    //
    // Some subscribers are not called when modifying codes.
    // The purpose of this broken cache feature is to prevent running without these subscribers
    protected bool $brokenCache = true; // Turned off in subscribers if everything fine.

    /**
     * @return bool
     */
    public function isBroken(): bool
    {
        return $this->brokenCache;
    }

    public function markCacheAsValid(): void
    {
        $this->brokenCache = false;
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
                    self::setMapping($classPath . "/".$namespace     , $baseClass     , $baseClassSwap);
                }
            }

            self::setMapping($this->getBundleLocation() . "/src/Tests"     , "Base\Tests"     , "App\Tests");
            self::setMapping($this->getBundleLocation() . "/src/Enum"      , "Base\Enum"      , "App\Enum");
            self::setMapping($this->getBundleLocation() . "/src/Notifier"  , "Base\Notifier"  , "App\Notifier");
            self::setMapping($this->getBundleLocation() . "/src/Form"      , "Base\Form"      , "App\Form");
            self::setMapping($this->getBundleLocation() . "/src/Entity"    , "Base\Entity"    , "App\Entity");
            self::setMapping($this->getBundleLocation() . "/src/Repository", "Base\Repository", "App\Repository");

            self::getAllClasses($this->getBundleLocation() . "/src/Database/Annotation");
            self::getAllClasses($this->getBundleLocation() . "/src/Annotations/Annotation");

            self::getAllClasses($this->getBundleLocation() . "/src/Enum");
            self::getAllClasses($this->getApplicationLocation() . "/src/Enum");

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
            $this->bootDoctrine = $this->bootDoctrine();
        }

        $this->boot = true;
    }

    public function bootDoctrine(): bool
    {
        /**
         * Turn all DateTime into UTC timezone in database
         */

        // Start session here to access client information
        $timezone = null;
        if (method_exists(User::class, "getCookie")) {
            $timezone = User::getCookie("timezone");
        }
        if (!in_array($timezone, timezone_identifiers_list())) {
            $timezone = "UTC";
        }

        // Set default time to UTC everywhere
        date_default_timezone_set($timezone);

        Type::overrideType('date', UTCDateTimeType::class);
        Type::overrideType('datetime', UTCDateTimeType::class);
        Type::overrideType('datetimetz', UTCDateTimeType::class);

        /**
         * @var EntityManagerInterface $this
         */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManagerConfig = $entityManager->getConfiguration();
        $entityManagerConnection = $entityManager->getConnection();

        /**
         * Testing doctrine connection
         */
        //try {
        //    $entityManagerConnection->connect();
        //} catch (Exception $e) {
        //    return false;
        //}

        /**
         * Doctrine custom configuration
         */
        // $entityManagerConfig
        //     ->setNamingStrategy(new \Base\Database\Mapping\NamingStrategy());
        // $entityManagerConfig
        //     ->setClassMetadataFactoryName(\Base\Database\Mapping\ClassMetadataFactory::class);
        $entityManagerConfig
            ->addFilter("trash_filter", TrashFilter::class);
        $entityManagerConfig
            ->addFilter("vault_filter", VaultFilter::class);
        $entityManagerConfig
            ->addCustomNumericFunction("rand", Rand::class);

        if (class_exists(Field::class)) {
            $entityManagerConfig
                ->addCustomNumericFunction("FIELD", Field::class);
        }

        if (class_exists(DqlFunctions\JsonExtract::class)) {
            $entityManagerConfig
                ->addCustomStringFunction(DqlFunctions\JsonExtract::FUNCTION_NAME, DqlFunctions\JsonExtract::class);
        }
        if (class_exists(DqlFunctions\JsonSearch::class)) {
            $entityManagerConfig
                ->addCustomStringFunction(DqlFunctions\JsonSearch::FUNCTION_NAME, DqlFunctions\JsonSearch::class);
        }
        if (class_exists(DqlFunctions\JsonContains::class)) {
            $entityManagerConfig
                ->addCustomStringFunction(DqlFunctions\JsonContains::FUNCTION_NAME, DqlFunctions\JsonContains::class);
        }

        $entityManagerConfig->setDefaultQueryHint(
            Query::HINT_CUSTOM_TREE_WALKERS,
            [/* No default tree walker for the moment */]
        );

        /**
         * Doctrine custom types: (priority to \App namespace)
         */

        $entityManagerConnection
            ->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        $entityManagerConnection
            ->getDatabasePlatform()->registerDoctrineTypeMapping('set', 'array');

        $classList = array_merge(
            self::getAllClasses(self::getBundleLocation() . "/src/Enum"),
            self::getAllClasses($this->getApplicationLocation() . "/src/Enum")
        );

        foreach ($classList as $className) {
            if (Type::hasType($className::getStaticName())) {
                Type::overrideType($className::getStaticName(), $className);
            } else {
                Type::addType($className::getStaticName(), $className);
            }
        }

        /**
         * Doctrine filters
         */
        $entityManager->getFilters()
            ->enable("trash_filter");

        $entityManager->getFilters()
            ->enable("vault_filter")
            ->setEnvironment($this->getEnvironment());

        return true;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->container = $container;

        if (!self::$cache) {
            $this->warmUp();
        }

        $container->addCompilerPass(new AnnotationPass());
        $container->addCompilerPass(new IconProviderPass());
        $container->addCompilerPass(new EntityExtensionPass());
        $container->addCompilerPass(new SharerPass());
        $container->addCompilerPass(new TradingMarketPass());
        $container->addCompilerPass(new TagRendererPass());
        $container->addCompilerPass(new ObfuscatorCompressionPass());
        $container->addCompilerPass(new WorkflowPass());

        /* Register aliased repositories */
        foreach (self::$aliasRepositoryList as $baseRepository => $aliasedRepository) {
            $container->register($baseRepository)->addTag("doctrine.repository_service")
                ->addArgument(new Reference('doctrine'));

            if ($aliasedRepository) {
                
                $container->register($aliasedRepository)
                    ->addTag("doctrine.repository_service")
                    ->addArgument(new Reference('doctrine'));
            }
        }
    }
}
