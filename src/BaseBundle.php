<?php

namespace Base;

$_SERVER["APP_TIMER"] = microtime(true);
include_once("Functions.php");

use DoctrineExtensions\Query\Mysql\Field;
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

class BaseBundle extends Bundle
{
    use SingletonTrait;
    public function __construct()
    {
        if (!$this->hasInstance()) {
            self::$_instance = $this;
        }
    }

    public static $sessionStorage = null;
    public const VERSION   = '1.0.0';
    public const USE_CACHE = true;

    public function getProjectDir()
    {
        return $this->container->getParameter('kernel.project_dir');
    }
    public function getPublicDir()
    {
        return $this->container->getParameter('kernel.project_dir')."/public/";
    }
    public function getSourceDir()
    {
        return $this->container->getParameter('kernel.project_dir')."/src/";
    }
    public function getCacheDir()
    {
        return $this->container->getParameter('kernel.cache_dir');
    }
    public function getEnvironment()
    {
        return $this->container->getParameter('kernel.environment');
    }

    protected bool $boot = false;
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
        $needsWarmup = !file_exists($this->getCacheDir()."/pools/base/bundle.php");
        self::$cache = new PhpArrayAdapter($this->getCacheDir() . "/pools/base/bundle.php", new FilesystemAdapter());
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

        if ($needsWarmup) {

            $this->setMapping("./Enum", "Base\Enum", "App\Enum");
            $this->setMapping("./Notifier", "Base\Notifier", "App\Notifier");

            $this->setMapping("./Form", "Base\Form", "App\Form");
            $this->setMapping("./Entity", "Base\Entity", "App\Entity");
            $this->setMapping("./Repository", "Base\Repository", "App\Repository");

            $this->getAllClasses($this->getBundleLocation() . "./Database/Annotation");
            $this->getAllClasses($this->getBundleLocation() . "./Annotations/Annotation");

            $this->getAllClasses($this->getBundleLocation() . "./Enum");
            $this->getAllClasses($this->getSourceDir() . "./Enum");

            self::$cache->warmUp([
                "base.file_paths"            => self::$filePaths ?? [],
                "base.classes"               => self::$classes ?? [],
                "base.alias_list"            => self::$aliasList ?? [],
                "base.alias_repository_list" => self::$aliasRepositoryList ?? []
            ]);
        }
    }

    public function boot()
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
         * @var EntityManagerInterface
         */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManagerConfig  = $entityManager->getConfiguration();
        $entityManagerConnection = $entityManager->getConnection();

        /**
         * Testing doctrine connection
         */
        try {
            $entityManagerConnection->connect();
        } catch (\Exception $e) {
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
            ->addCustomNumericFunction("rand", \Base\Database\Function\Rand::class);

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

    public function build(ContainerBuilder $container)
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

    public function getBundleLocation()
    {
        return dirname((new \ReflectionClass(self::class))->getFileName()) . "/";
    }

    public function setMapping(string $location = "./", string $inputNamespace = "", string $outputNamespace = "")
    {
        $classList = $this->getAllClasses($this->getBundleLocation() . $location, $inputNamespace);

        $aliasList = [];
        foreach ($classList as $class) {
            $aliasList[$inputNamespace."\\".$class] = $outputNamespace."\\".$class;
        }

        $this->setAlias($aliasList);
    }

    protected static ?array $aliasList = null;
    protected static ?array $aliasRepositoryList = null;
    public function getAlias($arrayOrObjectOrClass)
    {
        if (!$arrayOrObjectOrClass) {
            return $arrayOrObjectOrClass;
        }
        if (is_array($arrayOrObjectOrClass)) {
            return array_map(fn ($a) => $this->getAlias($a), $arrayOrObjectOrClass);
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
            } catch (\ErrorException $e) {
            }

            $outputExists = false;
            try {
                $outputExists = class_exists($output);
            } catch (\ErrorException $e) {
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

    public function setAliasEntity($class)
    {
        if (is_array($class)) {
            $classes = $class;
            foreach ($classes as $class) {
                $this->setAlias([
                    "Base\\Entity\\" . $class                => "App\\Entity\\" . $class,
                    "Base\\Entity\\" . $class . "Repository" => "App\\Entity\\" . $class . "Repository"
                ]);
            }

            return;
        }

        $this->setAlias([
            "Base\\Entity\\" . $class                => "App\\Entity\\" . $class,
            "Base\\Entity\\" . $class . "Repository" => "App\\Entity\\" . $class . "Repository"
        ]);
    }

    protected static ?array $classes = null;
    public function getAllClasses($path, $prefix = ""): array
    {
        $fullpath = realpath($path)." << ".$prefix;

        if (array_key_exists($fullpath, self::$classes)) {
            return self::$classes[$fullpath];
        }

        self::$classes[$fullpath] = self::$classes[$fullpath] ?? [];
        foreach ($this->getFilePaths($path) as $filename) {
            if (filesize($filename) == 0) {
                continue;
            }
            if (str_ends_with($filename, "Interface")) {
                continue;
            }

            self::$classes[$fullpath][] = $this->getFullNamespace($filename, $prefix) . $this->getClassname($filename);
        }

        return self::$classes[$fullpath];
    }

    public function getAllNamespaces($path, $prefix = ""): array
    {
        $namespaces = [];

        $filenames = $this->getFilePaths($path);
        foreach ($filenames as $filename) {
            if (filesize($filename) == 0) {
                continue;
            }

            $namespaces[] = rtrim($this->getFullNamespace($filename, $prefix), "\\");
        }

        return array_unique($namespaces);
    }

    public function getAllNamespacesAndClasses($path, $prefix = ""): array
    {
        $namespacesAndClasses = [];

        $filenames = $this->getFilePaths($path);
        foreach ($filenames as $filename) {
            if (filesize($filename) == 0) {
                continue;
            }

            $namespace = $this->getFullNamespace($filename, $prefix);
            $className = $this->getClassname($filename);
            if (str_ends_with($className, "Interface")) {
                continue;
            }

            $namespacesAndClasses[] = rtrim($namespace, "\\");
            $namespacesAndClasses[] = $namespace . $className;
        }

        return array_unique($namespacesAndClasses);
    }

    public function getClassname($filename)
    {
        $directoriesAndFilename = explode('/', $filename);
        $filename = array_pop($directoriesAndFilename);
        $nameAndExtension = explode('.', $filename);
        $className = array_shift($nameAndExtension);
        return $className;
    }

    public function getFullNamespace($filename, $prefix = "")
    {
        $lines = file($filename);
        $array = preg_grep('/^namespace /', $lines);
        $namespace = array_shift($array);

        $match = [];
        if (preg_match('/^namespace (\\\\?)'. addslashes($prefix).'(\\\\?)(.*);$/', $namespace, $match)) {
            $array = array_pop($match);
            if (!empty($array)) {
                return $array."\\";
            }
        }

        return "";
    }

    protected static $cache = null;
    protected static ?array $filePaths = null;
    public function getFilePaths($path)
    {
        $path = realpath($path);
        if (!file_exists($path)) {
            return [];
        }

        if (array_key_exists($path, self::$filePaths)) {
            return self::$filePaths[$path];
        }

        $finderFiles = Finder::create()->files()->in($path)->name('*.php');
        $files = [];
        foreach ($finderFiles as $finderFile) {
            $files[] = $finderFile->getRealpath();
        }

        self::$filePaths[$path] = $files;
        return $files;
    }
}
