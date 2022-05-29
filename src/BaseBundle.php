<?php

namespace Base;

use Base\DependencyInjection\Compiler\AnnotationPass;
use Base\DependencyInjection\Compiler\EntityExtensionPass;
use Base\DependencyInjection\Compiler\IconProviderPass;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Query;
use Exception;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use Symfony\Component\HttpKernel\Bundle\Bundle;

use Symfony\Component\Finder\Finder;

class BaseBundle extends Bundle
{
    public const CACHE   = true;
    public const VERSION = '1.0.0';

    public function boot()
    {
        $this->defineDoctrineTypes();
        $this->defineDoctrineFilters();
        $this->defineDoctrineWalkers();
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new AnnotationPass());
        $container->addCompilerPass(new IconProviderPass());
        $container->addCompilerPass(new EntityExtensionPass());

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

    public function defineDoctrineWalkers()
    {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManager->getConfiguration()->setDefaultQueryHint(
            Query::HINT_CUSTOM_TREE_WALKERS, [/* No default tree walker for the moment */]
        );
    }

    public function defineDoctrineFilters()
    {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManager->getFilters()->enable("trash_filter");
        $entityManager->getFilters()->enable("vault_filter");
    }

    public function defineDoctrineTypes()
    {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        try {
            $entityManager->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
            $entityManager->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('set', 'array');
        } catch(Exception $e) { }

        $projectDir = $this->container->get('kernel')->getProjectDir()."/src/";
        $classList = BaseBundle::getAllClasses($projectDir . "./Enum");
        $classList = array_merge(BaseBundle::getAllClasses(self::getBundleLocation() . "./Enum"), $classList);

        /* Register enum types: priority to App namespace */
        foreach($classList as $className) {

            if(Type::hasType($className::getStaticName())) Type::overrideType($className::getStaticName(), $className);
            else Type::addType($className::getStaticName(), $className);

            try { $entityManager->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping($className::getStaticName()."_db", $className::getStaticName()); }
            catch(Exception $e) { }
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
    public static function getAlias($alias) { return self::$aliasList[$alias] ?? $alias; }
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

        $filenames = self::getFilenames($path);
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

        $filenames = self::getFilenames($path);
        foreach ($filenames as $filename) {

            if(filesize($filename) == 0) continue;

            $namespaces[] = rtrim(self::getFullNamespace($filename, $prefix),"\\");
        }
        return array_unique($namespaces);
    }

    public static function getAllNamespacesAndClasses($path, $prefix = ""): array
    {
        $namespacesAndClasses = [];

        $filenames = self::getFilenames($path);
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

    public static function getFilenames($path)
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
BaseBundle::setMapping("./Form",       "Base\Form",       "App\Form");
BaseBundle::setMapping("./Entity",     "Base\Entity",     "App\Entity");
BaseBundle::setMapping("./Repository", "Base\Repository", "App\Repository");

include_once("Functions.php");
