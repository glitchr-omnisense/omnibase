<?php

namespace Base;

$_SERVER["APP_TIMER"] = microtime(true);
include_once("Functions.php");

use Base\Database\Function\Rand;
use DoctrineExtensions\Query\Mysql\Field;
use ErrorException;
use Exception;
use ReflectionClass;
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
use Base\Traits\SingletonTrait;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

use Symfony\Component\HttpKernel\Bundle\Bundle;

use Symfony\Component\Finder\Finder;

/**
 *
 */
class BaseBundle extends Bundle
{
    use SingletonTrait;

    public function __construct()
    {
        if (!$this->hasInstance()) {
            self::$_instance = $this;
        }
    }

    public static bool $firstClear = true;
    public static function markAsFirstClear(bool $first = true) { self::$firstClear = $first; }
    public static function isFirstClear() { return self::$firstClear; }

    public static $sessionStorage = null;
    public const VERSION = '1.0.0';
    public const USE_CACHE = true;

    /**
     * @return array|bool|float|int|string|\UnitEnum|null
     */
    public function getProjectDir()
    {
        return $this->container->getParameter('kernel.project_dir');
    }

    /**
     * @return string
     */
    public function getPublicDir()
    {
        return $this->container->getParameter('kernel.project_dir') . "/public/";
    }

    /**
     * @return string
     */
    public function getSourceDir()
    {
        return $this->container->getParameter('kernel.project_dir') . "/src/";
    }

    /**
     * @return array|bool|float|int|string|\UnitEnum|null
     */
    public function getCacheDir()
    {
        return $this->container->getParameter('kernel.cache_dir');
    }

    /**
     * @return array|bool|float|int|string|\UnitEnum|null
     */
    public function getEnvironment()
    {
        return $this->container->getParameter('kernel.environment');
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

    //
    // Some subscribers are not called when modifying codes.
    // The purpose of this broken cache feature is to prevent running without these subscribers
    protected bool $brokenCache = true; // Turn off in subscriber if everything fine.

    /**
     * @return bool
     */
    public function isBroken()
    {
        return $this->brokenCache;
    }

    public function markCacheAsValid()
    {
        $this->brokenCache = false;
    }

    public function warmUp()
    {
        $needsWarmup = !file_exists($this->getCacheDir() . "/pools/base/bundle.php");

        self::$cache = new PhpArrayAdapter($this->getCacheDir() . "/pools/base/bundle.php", new FilesystemAdapter("", 0, $this->getCacheDir() . "/pools/base/fallback"));
        self::$filePaths = self::$filePaths ?? self::$cache->getItem('base.file_paths')->get() ?? [];
        self::$classes = self::$classes ?? self::$cache->getItem('base.classes')->get() ?? [];
        self::$aliasList = self::$aliasList ?? self::$cache->getItem('base.alias_list')->get() ?? [];
        self::$aliasRepositoryList = self::$aliasRepositoryList ?? self::$cache->getItem('base.alias_repository_list')->get() ?? [];

        foreach (self::$aliasList as $class => $alias) {
            class_alias($class, $alias);
        }
        foreach (self::$aliasRepositoryList as $class => $alias) {
            class_alias($class, $alias);
        }

        $baseClassBundles = array_ends_with($this->getDeclaredClasses("Base", 1), "Bundle", ARRAY_USE_VALUES);
        sort($baseClassBundles);

        if ($needsWarmup) {
         
            foreach(array_reverse($baseClassBundles) as $classBundle)
            {
                $classBundlePath = explode("\\", $classBundle);
                
                array_pop($classBundlePath);

                $namespace = $classBundlePath[1] ?? "";
                $baseNamespace = $namespace ? "Base\\".$namespace : "Base";
                
                $classRefl = new \ReflectionClass($classBundle);
                $namespacePath = dirname($classRefl->getFileName());

                $this->setMapping($namespacePath . "/Controller", $baseNamespace."\Controller", "Base\Controller\\".$namespace);

                $this->setMapping($namespacePath . "/Tests"     , $baseNamespace."\Tests"     , "App\Tests\\".$namespace);
                $this->setMapping($namespacePath . "/Enum"      , $baseNamespace."\Enum"      , "App\Enum\\".$namespace);
                $this->setMapping($namespacePath . "/Notifier"  , $baseNamespace."\Notifier"  , "App\Notifier\\".$namespace);
                $this->setMapping($namespacePath . "/Form"      , $baseNamespace."\Form"      , "App\Form\\".$namespace);
                $this->setMapping($namespacePath . "/Entity"    , $baseNamespace."\Entity"    , "App\Entity\\".$namespace);
                $this->setMapping($namespacePath . "/Repository", $baseNamespace."\Repository", "App\Repository\\".$namespace);

                $this->getAllClasses($namespacePath . "./Database/Annotation/".$namespace);
                $this->getAllClasses($namespacePath . "./Annotations/Annotation/".$namespace);
                $this->getAllClasses($namespacePath . "./Enum/".$namespace);
            }

            $this->getAllClasses($this->getSourceDir() . "./Enum/".$namespace);
            self::$cache->warmUp([
                "base.file_paths" => self::$filePaths ?? [],
                "base.classes" => self::$classes ?? [],
                "base.alias_list" => self::$aliasList ?? [],
                "base.alias_repository_list" => self::$aliasRepositoryList ?? []
            ]);
        }
    }

    public function getDeclaredClasses(string $namespace = "", int $level = -1)
    {
        $namespace .= '\\';
        return array_values(array_unique(array_filter(get_declared_classes(), function($item) use ($namespace, $level) 
        { 
            if(substr($item, 0, strlen($namespace)) !== $namespace) return false;
            if($level < 0) return true;

            return $level >= substr_count(substr($item, strlen($namespace)), "\\");
        })));
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


    protected static array $dumpEnabled = [];

    public static function enableDump(string $scope)
    {
        self::$dumpEnabled[$scope] = true;
    }

    public static function disableDump(string $scope)
    {
        self::$dumpEnabled[$scope] = false;
    }

    /**
     * @param $scope
     * @param ...$variadic
     * @return void
     */
    public static function dump($scope, ...$variadic)
    {
        if (self::$dumpEnabled[$scope] ?? false) {
            dump(...$variadic);
        }
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
        try {
            $entityManagerConnection->connect();
        } catch (Exception $e) {
            return false;
        }

        /**
         * Doctrine custom configuration
         */
        // $entityManagerConfig
        //     ->setNamingStrategy(new \Base\Database\Mapping\NamingStrategy());
        // $entityManagerConfig
        //     ->setClassMetadataFactoryName(\Base\Database\Mapping\Factory\ClassMetadataFactory::class);
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
            $this->getAllClasses($this->getBundleLocation() . "./Enum"),
            $this->getAllClasses($this->getSourceDir() . "./Enum")
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

    /**
     * @return string
     */
    public function getBundleLocation()
    {
        return dirname((new ReflectionClass(self::class))->getFileName()) . "/";
    }

    public function setMapping(string $path, string $inputNamespace = "", string $outputNamespace = "")
    {
        $classList = $this->getAllClasses($path, $inputNamespace);

        $aliasList = [];
        foreach ($classList as $class) {
            $aliasList[$inputNamespace . "\\" . $class] = str_rstrip($outputNamespace, "\\"). "\\" . $class;
        }

        $this->setAlias($aliasList);
    }

    protected static ?array $aliasList = null;
    protected static ?array $aliasRepositoryList = null;

    /**
     * @param $arrayOrObjectOrClass
     * @return array|array[]|false|false[]|mixed|string|string[]
     */
    public function getAlias($arrayOrObjectOrClass)
    {
        if (!$arrayOrObjectOrClass) {
            return $arrayOrObjectOrClass;
        }
        if (is_array($arrayOrObjectOrClass)) {
            return array_map(fn($a) => $this->getAlias($a), $arrayOrObjectOrClass);
        }

        $arrayOrObjectOrClass = is_object($arrayOrObjectOrClass) ? get_class($arrayOrObjectOrClass) : $arrayOrObjectOrClass;
        if (!class_exists($arrayOrObjectOrClass)) {
            return false;
        }

        return self::$aliasList[$arrayOrObjectOrClass] ?? $arrayOrObjectOrClass;
    }

    public function hasAlias(mixed $objectOrClass): bool
    {
        if (!is_object($objectOrClass) && !is_string($objectOrClass)) {
            return false;
        }

        $class = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;
        if (!class_exists($class)) {
            return false;
        }

        return $this->getAlias($class) != $class;
    }

    /**
     * @param $aliasRepository
     * @return mixed
     */
    public function getAliasRepository($aliasRepository)
    {
        return self::$aliasRepositoryList[$aliasRepository] ?? $aliasRepository;
    }

    public function setAlias(array $classes)
    {
        foreach ($classes as $input => $output) {
            // Autowire base repositories
            $inputExists = false;
            try {
                $inputExists = class_exists($input);
            } catch (ErrorException $e) {
            }

            $outputExists = false;
            try {
                $outputExists = class_exists($output);
            } catch (ErrorException $e) {
            }

            if ($inputExists && !$outputExists && !array_key_exists($input, self::$aliasList)) {
                class_alias($input, $output);

                if (str_ends_with($input, "Repository")) {
                    self::$aliasRepositoryList[$input] = $output;
                } else {
                    self::$aliasList[$input] = $output;
                }
            }
        }
    }

    /**
     * @param $class
     * @return void
     */
    public function setAliasEntity($class)
    {
        if (is_array($class)) {
            $classes = $class;
            foreach ($classes as $class) {
                $this->setAlias([
                    "Base\\Entity\\" . $class => "App\\Entity\\" . $class,
                    "Base\\Entity\\" . $class . "Repository" => "App\\Entity\\" . $class . "Repository"
                ]);
            }

            return;
        }

        $this->setAlias([
            "Base\\Entity\\" . $class => "App\\Entity\\" . $class,
            "Base\\Entity\\" . $class . "Repository" => "App\\Entity\\" . $class . "Repository"
        ]);
    }

    protected static ?array $classes = null;

    /**
     * @param $path
     * @param $prefix
     * @param $level
     * @return array
     */
    public function getAllClasses(string $path, string $prefix = "", int $level = -1): array
    {
        $fullpath = realpath($path) . " " . $prefix ." (".$level.")";
        if (!array_key_exists($fullpath, self::$classes)) {
        
            self::$classes[$fullpath] = self::$classes[$fullpath] ?? [];
            foreach ($this->getFilePaths($path, $level) as $filename) {
                if (filesize($filename) == 0) {
                    continue;
                }
                if (str_ends_with($filename, "Interface")) {
                    continue;
                }

                self::$classes[$fullpath][] = $this->getFullNamespace($filename, $prefix) . $this->getClassname($filename);
            }

            self::$classes[$fullpath] = array_unique(self::$classes[$fullpath]);
        }

        return self::$classes[$fullpath];
    }

    protected static ?array $namespaces = null;

    /**
     * @param $path
     * @param $prefix
     * @param $level
     * @return array
     */
    public function getAllNamespaces(string $path, string $prefix = "", int $level = -1): array
    {    
        $fullpath = realpath($path) . " " . $prefix ." (".$level.")";
        if (!array_key_exists($fullpath, self::$namespaces)) {

            self::$namespaces[$fullpath] = self::$namespaces[$fullpath] ?? [];
            foreach ($this->getFilePaths($path, $level) as $filename) {
                if (filesize($filename) == 0) {
                    continue;
                }

                self::$namespaces[$fullpath][] = rtrim($this->getFullNamespace($filename, $prefix), "\\");
            }

            self::$namespaces[$fullpath] = array_unique(self::$namespaces[$fullpath]);
        }

        return self::$namespaces[$fullpath];
    }

    /**
     * @param $path
     * @param $prefix
     * @param $level
     * @return array
     */
    public function getAllNamespacesAndClasses(string $path, string $prefix = "", int $level = -1): array
    {
        return array_merge($this->getAllNamespaces($path, $prefix, $level), $this->getAllClasses($path, $prefix, $level));
    }

    /**
     * @param $filename
     * @return string|null
     */
    public function getClassname(string $filename)
    {
        $directoriesAndFilename = explode('/', $filename);
        $filename = array_pop($directoriesAndFilename);
        $nameAndExtension = explode('.', $filename);
        return array_shift($nameAndExtension);
    }

    /**
     * @param $filename
     * @param $prefix
     * @return string
     */
    public function getFullNamespace(string $filename, string $prefix = "")
    {
        $lines = file($filename);
        $array = preg_grep('/^namespace /', $lines);
        $namespace = array_shift($array);

        $match = [];
        if (preg_match('/^namespace (\\\\?)' . addslashes($prefix) . '(\\\\?)(.*);$/', $namespace, $match)) {
            $array = array_pop($match);
            if (!empty($array)) {
                return $array . "\\";
            }
        }

        return "";
    }

    protected static $cache = null;
    protected static ?array $filePaths = null;

    /**
     * @param $path
     * @param $level
     * @return array|mixed
     */
    public function getFilePaths(string $path, int $level = -1)
    {
        $path = realpath($path);
        if (!file_exists($path)) {
            return [];
        }

        if (array_key_exists($path, self::$filePaths)) {
            return self::$filePaths[$path];
        }

        $finder = Finder::create();
        if($level > -1) $finder->depth(" < ".$level);

        $finderFiles = $finder->files()->in($path)->name('*.php');
        $files = [];
        foreach ($finderFiles as $finderFile) {
            $files[] = $finderFile->getRealpath();
        }

        self::$filePaths[$path] = $files;
        return $files;
    }
}
