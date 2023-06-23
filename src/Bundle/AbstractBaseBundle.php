<?php

namespace Base\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use ReflectionClass;
use ErrorException;

use Symfony\Component\Finder\Finder;

use Base\BaseBundle;
use Base\Traits\SingletonTrait;

abstract class AbstractBaseBundle extends Bundle
{
    use SingletonTrait;

    public function __construct()
    {
        if (!$this->hasInstance()) {
            self::$_instance = $this;
        }
    }

    /**
     * @return string
     */
    public function getBundleLocation()
    {
        return dirname((new ReflectionClass(static::class))->getFileName()) . "/";
    }
    
    protected static array $dumpEnabled = [];

    public static function enableDump (string $scope) { self::$dumpEnabled[$scope] = true; }
    public static function disableDump(string $scope) { self::$dumpEnabled[$scope] = false; }

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

    protected static ?array $bundles = null;
    public static function getBundles()
    {
        if(self::$bundles === null) {

            self::$bundles = array_filter(
                self::getDeclaredClasses("Base", 2), 
                fn($v) => str_ends_with($v, "Bundle") && $v != self::class && $v != BaseBundle::class
            );
        }

        return self::$bundles;
    }

    public static function hasBundle(string $bundleName)
    {
        $bundles = self::getBundles();
        $bundleNames = array_map(fn($c) => camel2snake(str_rstrip(basename_namespace($c), "Bundle")), $bundles);

        return in_array($bundleName, $bundles) || in_array($bundleName, $bundleNames);
    }

    public static function getDeclaredClasses(string $namespace = "", int $level = -1)
    {
        $namespace .= '\\';
        return array_values(array_unique(array_filter(get_declared_classes(), function($item) use ($namespace, $level) 
        { 
            if(substr($item, 0, strlen($namespace)) !== $namespace) return false;
            if($level < 0) return true;

            return $level >= substr_count(substr($item, strlen($namespace)), "\\");
        })));
    }

    public static function getDeclaredNamespaces(string $namespace = "", int $level = 1)
    {
        $namespace .= '\\';
        return array_values(array_unique(array_transforms(function($k, $v) use ($namespace,$level) : ?array{

            if(!str_starts_with($v, $namespace)) return null;
            return [$k, dirname_namespace($v, $level)];

        }, get_declared_classes())));
    }

    public static function setMapping(string $path, string $inputNamespace = "", string $outputNamespace = "")
    {
        $classList = self::getAllClasses($path, $inputNamespace);

        $aliasList = [];
        foreach ($classList as $class) {
            $aliasList[$inputNamespace . "\\" . $class] = str_rstrip($outputNamespace, "\\"). "\\" . $class;
        }

        self::setAlias($aliasList);
    }

    protected static ?array $aliasList = null;
    protected static ?array $aliasRepositoryList = null;

    /**
     * @param $arrayOrObjectOrClass
     * @return array|array[]|false|false[]|mixed|string|string[]
     */
    public static function getAlias($arrayOrObjectOrClass)
    {
        if (!$arrayOrObjectOrClass) {
            return $arrayOrObjectOrClass;
        }
        if (is_array($arrayOrObjectOrClass)) {
            return array_map(fn($a) => self::getAlias($a), $arrayOrObjectOrClass);
        }

        $arrayOrObjectOrClass = is_object($arrayOrObjectOrClass) ? get_class($arrayOrObjectOrClass) : $arrayOrObjectOrClass;
        if (!class_exists($arrayOrObjectOrClass)) {
            return false;
        }

        return self::$aliasList[$arrayOrObjectOrClass] ?? $arrayOrObjectOrClass;
    }

    public static function hasAlias(mixed $objectOrClass): bool
    {
        if (!is_object($objectOrClass) && !is_string($objectOrClass)) {
            return false;
        }

        $class = is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass;
        if (!class_exists($class)) {
            return false;
        }

        return self::getAlias($class) != $class;
    }

    /**
     * @param $aliasRepository
     * @return mixed
     */
    public static function getAliasRepository($aliasRepository)
    {
        return self::$aliasRepositoryList[$aliasRepository] ?? $aliasRepository;
    }

    public static function setAlias(array $classes)
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
    public static function setAliasEntity($class)
    {
        if (is_array($class)) {
            $classes = $class;
            foreach ($classes as $class) {
                self::setAlias([
                    "Base\\Entity\\" . $class => "App\\Entity\\" . $class,
                    "Base\\Entity\\" . $class . "Repository" => "App\\Entity\\" . $class . "Repository"
                ]);
            }

            return;
        }

        self::setAlias([
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
    public static function getAllClasses(string $path, string $prefix = "", int $level = -1): array
    {
        $fullpath = realpath($path) . " " . $prefix ." (".$level.")";
        if (!array_key_exists($fullpath, self::$classes)) {
        
            self::$classes[$fullpath] = self::$classes[$fullpath] ?? [];
            foreach (self::getFiles($path, $level) as $filename) {
                if (filesize($filename) == 0) {
                    continue;
                }
                if (str_ends_with($filename, "Interface")) {
                    continue;
                }

                self::$classes[$fullpath][] = self::getFullNamespace($filename, $prefix) . self::getClassname($filename);
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
    public static function getAllNamespaces(string $path, string $prefix = "", int $level = -1): array
    {    
        $fullpath = realpath($path) . " " . $prefix ." (".$level.")";
        if (!array_key_exists($fullpath, self::$namespaces)) {

            self::$namespaces[$fullpath] = self::$namespaces[$fullpath] ?? [];
            foreach (self::getFiles($path, $level) as $filename) {
                if (filesize($filename) == 0) {
                    continue;
                }

                self::$namespaces[$fullpath][] = rtrim(self::getFullNamespace($filename, $prefix), "\\");
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
    public static function getAllNamespacesAndClasses(string $path, string $prefix = "", int $level = -1): array
    {
        return array_merge(self::getAllNamespaces($path, $prefix, $level), self::getAllClasses($path, $prefix, $level));
    }

    /**
     * @param $filename
     * @return string|null
     */
    public static function getClassname(string $filename)
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
    public static function getFullNamespace(string $filename, string $prefix = "")
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
    protected static ?array $files = null;

    /**
     * @param $path
     * @param $level
     * @return array|mixed
     */
    public static function getFiles(string $path, int $level = -1)
    {
        $path = realpath($path);
        if (!file_exists($path)) {
            return [];
        }

        if (array_key_exists($path, self::$files)) {
            return self::$files[$path];
        }

        $finder = Finder::create();
        if($level > -1) $finder->depth(" < ".$level);

        $finderFiles = $finder->files()->in($path)->name('*.php');
        $files = [];
        foreach ($finderFiles as $finderFile) {
            $files[] = $finderFile->getRealpath();
        }

        self::$files[$path] = $files;
        return $files;
    }

    /**
     * @param $path
     * @param $level
     * @return array|mixed
     */
    public static function getDirectories(string $path, int $level = -1)
    {
        $path = realpath($path);
        if (!is_dir($path)) {
            return [];
        }

        if (array_key_exists($path, self::$files)) {
            return self::$files[$path];
        }

        $finder = Finder::create();
        if($level > -1) $finder->depth(" < ".$level);

        $finderFiles = $finder->directories()->in($path);
        $files = [];
        foreach ($finderFiles as $finderFile) {
            $files[] = $finderFile->getRealpath();
        }

        self::$files[$path] = $files;
        return $files;
    }
}
