<?php

namespace Base;

use App\Entity\User;
use Base\Database\Filter\TrashFilter;
use Base\Database\Filter\VaultFilter;
use Base\Database\Type\UTCDateTimeType;
use Base\DependencyInjection\Compiler\AnnotationPass;
use Base\DependencyInjection\Compiler\CurrencyApiPass;
use Base\DependencyInjection\Compiler\EntityExtensionPass;
use Base\DependencyInjection\Compiler\IconProviderPass;
use Base\DependencyInjection\Compiler\SharerPass;
use Base\DependencyInjection\Compiler\TagRendererPass;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use Symfony\Component\HttpKernel\Bundle\Bundle;

use Symfony\Component\Finder\Finder;

class BaseBundle extends Bundle
{
    public const CACHE   = true;
    public const VERSION = '1.0.0';

    protected static bool $boot = false;
    protected static bool $doctrineStartup = false;
    protected static bool $brokenCache = true; // Turn off in subscriber if everything fine.
    public static function isBooted() { return self::$boot; }

    public static function isBroken() { return self::$brokenCache; }
    public static function markCacheAsValid()
    {
        self::$brokenCache = false;
    }

    public function getProjectDir() { return $this->container->get('kernel')->getProjectDir()."/src/"; }
    public function getEnvironment() { return $this->container->get('kernel')->getEnvironment(); }

    public function boot()
    {
        self::$doctrineStartup = $this->doctrineStartup();
        self::$boot = true;
    }

    public static function hasDoctrine():bool { return self::$doctrineStartup; }
    public function doctrineStartup():bool
    {
        /**
         * Turn all DateTime into UTC timezone in database
         */

        // Start session here to access client information
        $timezone = method_exists(User::class, "getCookie") ? User::getCookie("timezone") : null;
        if( !in_array($timezone, timezone_identifiers_list()) )
            $timezone = "UTC";

        // Set default time to UTC everywhere
        date_default_timezone_set($timezone ?? "UTC");

        Type::overrideType('date', UTCDateTimeType::class);
        Type::overrideType('datetime', UTCDateTimeType::class);
        Type::overrideType('datetimetz', UTCDateTimeType::class);

        /**
         * @var EntityManagerInterface
         */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManagerConfig  = $entityManager->getConfiguration();

        /**
         * Testing doctrine connection
         */
        try { $entityManager->getConnection()->connect(); }
        catch (\Exception $e) { return false; }

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
        $entityManagerConfig->setDefaultQueryHint(
            Query::HINT_CUSTOM_TREE_WALKERS, [/* No default tree walker for the moment */]
        );

        /** 
         * Doctrine custom types: (priority to \App namespace) 
         */
        $entityManager->getConnection()
            ->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        $entityManager->getConnection()
            ->getDatabasePlatform()->registerDoctrineTypeMapping('set', 'array');

        $classList = array_merge(
            BaseBundle::getAllClasses(self::getBundleLocation() . "./Enum"), 
            BaseBundle::getAllClasses($this->getProjectDir() . "./Enum")
        );

        foreach($classList as $className) {

            if(Type::hasType($className::getStaticName())) Type::overrideType($className::getStaticName(), $className);
            else Type::addType($className::getStaticName(), $className);

            $entityManager->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping($className::getStaticName()."_db", $className::getStaticName());
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

        $container->addCompilerPass(new AnnotationPass());
        $container->addCompilerPass(new IconProviderPass());
        $container->addCompilerPass(new EntityExtensionPass());
        $container->addCompilerPass(new SharerPass());
        $container->addCompilerPass(new CurrencyApiPass());
        $container->addCompilerPass(new TagRendererPass());

        /* Register aliased repositories */
        foreach(self::$aliasRepositoryList as $baseRepository => $aliasedRepository) {

            $container->register($baseRepository)->addTag("doctrine.repository_service")
                      ->addArgument(new Reference('doctrine'));

            if($aliasedRepository) {

                $container->register($aliasedRepository)
                    ->addTag("doctrine.repository_service")
                    ->addArgument(new Reference('doctrine'));
            }
        }
    }

    const __ROOT__ = "Base\\BaseBundle";
    public static function getBundleLocation() {
        return dirname((new \ReflectionClass(self::__ROOT__))->getFileName()) . "/";
    }

    public static function setMapping(string $location = "./", string $inputNamespace = "", string $outputNamespace = "")
    {
        $classList = BaseBundle::getAllClasses(self::getBundleLocation() . $location, $inputNamespace);

        $aliasList = [];
        foreach ($classList as $class)
            $aliasList[$inputNamespace."\\".$class] = $outputNamespace."\\".$class;

        self::setAlias($aliasList);
    }

    public static $aliasList;
    public static $aliasRepositoryList = [];
    public static function getAlias($arrayOrObjectOrClass) 
    { 
        if(!$arrayOrObjectOrClass) return $arrayOrObjectOrClass;
        if(is_array($arrayOrObjectOrClass))
            return array_map(fn($a) => self::getAlias($a), $arrayOrObjectOrClass);

        $arrayOrObjectOrClass = is_object($arrayOrObjectOrClass) ? get_class($arrayOrObjectOrClass) : $arrayOrObjectOrClass;
        if(!class_exists($arrayOrObjectOrClass)) return false;

        return self::$aliasList[$arrayOrObjectOrClass] ?? $arrayOrObjectOrClass; 
    }

    public static function hasAlias(mixed $objectOrClass): bool
    {
        if(!is_object($objectOrClass) && !is_string($objectOrClass)) return false;

        $class = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;
        if(!class_exists($class)) return false;

        return self::getAlias($class) != $class;
    }

    public static function getAliasRepository($aliasRepository) { return self::$aliasRepositoryList[$aliasRepository] ?? $aliasRepository; }
    public static function setAlias(array $classes)
    {
        foreach ($classes as $input => $output) {

            // Autowire base repositories
            if (class_exists($input) && !class_exists($output)) {

                class_alias($input, $output);

                if (str_ends_with($input, "Repository")) self::$aliasRepositoryList[$input] = $output;
                else self::$aliasList[$input] = $output;
            }

        }
    }

    public static function setAliasEntity($class)
    {
        if (is_array($class)) {

            $classes = $class;
            foreach ($classes as $class) {
                self::setAlias([
                    "Base\\Entity\\" . $class              => "App\\Entity\\" . $class,
                    "Base\\Entity\\" . $class . "Repository" => "App\\Entity\\" . $class . "Repository"
                ]);
            }

            return;
        }

        self::setAlias([
            "Base\\Entity\\" . $class              => "App\\Entity\\" . $class,
            "Base\\Entity\\" . $class . "Repository" => "App\\Entity\\" . $class . "Repository"
        ]);
    }

    public static function getAllClasses($path, $prefix = ""): array
    {
        $classes = [];

        $filenames = self::getFilePaths($path);
        foreach ($filenames as $filename) {

            if(filesize($filename) == 0) continue;
            if(str_ends_with($filename, "Interface")) continue;

            $classes[] = self::getFullNamespace($filename, $prefix) . self::getClassname($filename);
        }

        return $classes;
    }

    public static function getAllNamespaces($path, $prefix = ""): array
    {
        $namespaces = [];

        $filenames = self::getFilePaths($path);
        foreach ($filenames as $filename) {

            if(filesize($filename) == 0) continue;

            $namespaces[] = rtrim(self::getFullNamespace($filename, $prefix),"\\");
        }
        return array_unique($namespaces);
    }

    public static function getAllNamespacesAndClasses($path, $prefix = ""): array
    {
        $namespacesAndClasses = [];

        $filenames = self::getFilePaths($path);
        foreach ($filenames as $filename) {

            if(filesize($filename) == 0) continue;

            $namespace = self::getFullNamespace($filename, $prefix);
            $className = self::getClassname($filename);
            if(str_ends_with($className, "Interface")) continue;

            $namespacesAndClasses[] = rtrim($namespace, "\\");
            $namespacesAndClasses[] = $namespace . $className;
        }

        return array_unique($namespacesAndClasses);
    }

    public static function getClassname($filename)
    {
        $directoriesAndFilename = explode('/', $filename);
        $filename = array_pop($directoriesAndFilename);
        $nameAndExtension = explode('.', $filename);
        $className = array_shift($nameAndExtension);
        return $className;
    }

    public static function getFullNamespace($filename, $prefix = "")
    {
        $lines = file($filename);
        $array = preg_grep('/^namespace /', $lines);
        $namespace = array_shift($array);

        $match = [];
        if( preg_match('/^namespace (\\\\?)'. addslashes($prefix).'(\\\\?)(.*);$/', $namespace, $match) ) {

            $array = array_pop($match);
            if(!empty($array)) return $array."\\";
        }

        return "";
    }

    public static function getFilePaths($path)
    {
        if(!file_exists($path)) return [];

        $finderFiles = Finder::create()->files()->in($path)->name('*.php');
        $filenames = [];
        foreach ($finderFiles as $finderFile)
            $filenames[] = $finderFile->getRealpath();

        return $filenames;
    }
}

BaseBundle::setMapping("./Enum",       "Base\Enum",       "App\Enum");
BaseBundle::setMapping("./Notifier",   "Base\Notifier",   "App\Notifier");

BaseBundle::setMapping("./Form",       "Base\Form",       "App\Form");
BaseBundle::setMapping("./Entity",     "Base\Entity",     "App\Entity");
BaseBundle::setMapping("./Repository", "Base\Repository", "App\Repository");

include_once("Functions.php");
