<?php

namespace Base\Service;

use Base\Exception\NotDeletableException;
use Base\Exception\NotReadableException;
use Base\Exception\NotWritableException;

use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\PathPrefixer;

use League\Flysystem\PhpseclibV3\SftpAdapter;

use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\FlysystemBundle\Lazy\LazyFactory;
use Symfony\Component\Finder\Finder;
use InvalidArgumentException;
use League\Flysystem\UnableToCreateDirectory;
use ReflectionException;

class Flysystem extends LazyFactory implements FlysystemInterface
{
    /**
     * @var FilesystemOperator
     */
    protected FilesystemOperator $operator;

    protected static $projectDir;
    public static function getProjectDir()
    {
        return self::$projectDir;
    }

    protected static $publicDir;
    public static function getPublicDir()
    {
        return self::$publicDir;
    }

    public function __construct(...$args)
    {
        parent::__construct(...$args);

        self::$projectDir = dirname(__FILE__, 6);
        self::$publicDir  = self::$projectDir."/public";

        if (!$this->hasStorage("local.storage")) {
            throw new InvalidArgumentException("\"local.storage\" storage not found in your Flysystem configuration.");
        }

        $this->setDefaultStorage("local.storage");
    }

    public function createStorage(string $source, ?string $storageName = null)
    {
        if ($storageName === null) {
            $storageName = $source;
        }

        // NB: Nah.. I never experienced this..
        // if ($source === $storageName) {
        //     throw new \InvalidArgumentException('The "lazy" adapter source is referring to itself as "'.$source.'", which would lead to infinite recursion.');
        // }

        if (!$this->storages->has($source)) {
            throw new \InvalidArgumentException('You have requested a non-existent source storage "'.$source.'" in lazy storage "'.$storageName.'".');
        }

        return $this->storages->get($source);
    }

    public function hasStorage(string $storageName): bool
    {
        return array_key_exists($storageName, $this->storages->getProvidedServices());
    }
    public function getStorageNames(bool $public = true): array
    {
        return array_filter(
            array_keys($this->storages->getProvidedServices()),
            fn ($s) => $public || !str_ends_with($s, ".public"),
        );
    }

    public function getDefaultStorage(): FilesystemOperator
    {
        return $this->operator;
    }
    public function setDefaultStorage(FilesystemOperator|string $operator)
    {
        $this->operator = $this->getOperator($operator);
        return $this;
    }

    public function getOperator(FilesystemOperator|string|null $operator = null): FilesystemOperator
    {
        if (class_implements_interface($operator, FilesystemOperator::class)) {
            return $operator;
        }

        if (is_string($operator)) {
            if (!$this->hasStorage($operator)) {
                throw new InvalidArgumentException("\"".$operator."\" storage not found in your Flysystem configuration.");
            }

            return $this->createStorage($operator);
        }

        return $this->operator;
    }

    public function getAdapter(FilesystemOperator|string|null $operator = null): FilesystemAdapter
    {
        $operator = $this->getOperator($operator);

        try {
            $reflProperty = new \ReflectionProperty(get_class($operator), 'adapter');
        } catch (ReflectionException $e) {
            return null;
        }

        $reflProperty->setAccessible(true);
        return $reflProperty->getValue($operator);
    }

    protected function getPathPrefixer(FilesystemOperator|string|null $operator = null): PathPrefixer
    {
        $adapter = $this->getAdapter($operator);

        //
        // Prefixer
        try {
            $reflProperty = new \ReflectionProperty(get_class($adapter), 'prefixer');
        } catch (ReflectionException $e) {
            return null;
        }

        $reflProperty->setAccessible(true);
        return $reflProperty->isInitialized($adapter) ? $reflProperty->getValue($adapter) : new PathPrefixer($this->getConnectionOptions($operator)["root"] ?? "/");
        ;
    }

    public function getConnectionOptions(FilesystemOperator|string|null $operator = null): ?array
    {
        $adapter = $this->getAdapter($operator);

        //
        // Connection options
        try {
            $reflProperty = new \ReflectionProperty(get_class($adapter), 'connectionOptions');
        } catch (ReflectionException $e) {
            // Connection provider (SFTP)
            try {
                $reflProperty = new \ReflectionProperty(get_class($adapter), 'connectionProvider');
            } catch (ReflectionException $e) {
                return null;
            }
        }

        $reflProperty->setAccessible(true);
        return $reflProperty->isInitialized($adapter) ? to_array($reflProperty->getValue($adapter)) : null;
    }

    public function prefixPath(string $path, FilesystemOperator|string|null $operator = null)
    {
        $prefixPath = $this->getPathPrefixer($operator)?->prefixPath("") ?? "";
        return $prefixPath.str_lstrip($path, $prefixPath);
    }

    public function stripPrefix(string $path, FilesystemOperator|string|null $operator = null)
    {
        $prefixPath = $this->getPathPrefixer($operator)?->prefixPath("") ?? "";
        return str_lstrip($path, $prefixPath);
    }

    public function read(string $path, FilesystemOperator|string|null $operator = null): ?string
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if (!$this->fileExists($path, $operator)) {
            return null;
        }

        try {
            return $operator->read($path);
        } catch (UnableToReadFile $e) {
            throw new NotReadableException("Unable to read file \"$path\".. ".$e->getMessage());
        }
    }

    public function write(string $path, string $contents, FilesystemOperator|string|null $operator = null, array $config = []): bool
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if ($this->fileExists($path, $operator)) {
            return false;
        }

        try {
            $operator->write($path, $contents, $config);
        } catch (UnableToWriteFile $e) {
            throw new NotWritableException("Unable to write file \"$path\".. ".$e->getMessage());
        }
        return true;
    }

    public function delete(string $path, FilesystemOperator|string|null $operator = null): bool
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if (!$this->fileExists($path, $operator)) {
            return false;
        }

        try {
            $operator->delete($path);
        } catch (UnableToDeleteFile|UnableToDeleteDirectory $e) {
            throw new NotDeletableException("Unable to delete file \"$path\".. ".$e->getMessage());
        }
        return true;
    }

    public function fileExists(string $path, FilesystemOperator|string|null $operator = null): bool
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        try {
            return $operator->fileExists($path);
        } catch (CorruptedPathDetected $e) {
            return false;
        }
    }

    public function mkdir(string $path, FilesystemOperator|string|null $operator = null, array $config = []): bool
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if ($this->fileExists($path, $operator)) {
            return false;
        }

        try {
            $operator->createDirectory($path, $config);
        } catch (UnableToDeleteFile|UnableToDeleteDirectory $e) {
            throw new NotDeletableException("Unable to create directory \"$path\".. ".$e->getMessage());
        }
        return true;
    }

    public function mimeType(string $path, FilesystemOperator|string|null $operator = null): ?string
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if (!$this->fileExists($path, $operator)) {
            return null;
        }

        try {
            return $operator->mimeType($path);
        } catch (UnableToRetrieveMetadata $e) {
            throw new NotDeletableException("Unable to read mimetype \"$path\".. ".$e->getMessage());
        }
        return null;
    }

    public function get(mixed $path, FilesystemOperator|string|null $operator = null)
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if ($this->fileExists($path, $operator)) {
            return $this->getPathPrefixer($operator)?->prefixPath($path);
        }

        return null;
    }

    protected function getPublicRealpath(?string $path = null, int $depth = 1): array
    {
        $publicPath = realpath($this->getPublicDir()."/".str_lstrip($path, [$this->getPublicDir(), "/"]));

        $endpoints = [$publicPath => realpath($publicPath)];
        foreach (Finder::create()->followLinks()->directories()->in($publicPath)->depth("< ".$depth) as $path) {
            $endpoints[$path->getPathname()] = realpath($path->getPathname());
        }

        return $endpoints;
    }

    public function getPublicRoot(FilesystemOperator|string|null $operator = null): ?string
    {
        try {
            $publicPath = $this->getPathPrefixer($this->getOperator($operator))?->prefixPath("") ?? null;
        } catch (UnableToCreateDirectory $e) {
            $publicPath = $e->location();
        }

        return $publicPath;
    }

    public function isRemote(FilesystemOperator|string|null $operator = null)
    {
        $adapter = $this->getAdapter($operator);
        return property_exists($adapter, "connectionOptions") || property_exists($adapter, "connectionProvider");
    }

    public function getPublic(mixed $path, FilesystemOperator|string|null $operator = null)
    {
        if ($path === null) {
            return null;
        }
        if (in_array($path, ["", "/"])) {
            return $this->getPublicRoot($operator);
        }

        $path = $this->stripPrefix($path, $operator);
        $path = $this->getPathPrefixer($operator)?->prefixPath($path) ?? null;
        if ($path === null) {
            return null;
        }

        //
        // Check if file is reacheable from /public directory
        $operator = $this->getOperator($operator);
        if ($operator) {
            $endpoints = $this->getPublicRealpath();
            foreach ($endpoints as $alias => $realpath) {
                if (str_starts_with($path, $realpath) && file_exists($alias.str_lstrip($path, $realpath))) {
                    return $alias.str_lstrip($path, $realpath);
                }
            }
        }

        //
        // Check if the corresponding public operator is found
        if (is_string($operator)) {
            $operator = $this->getOperator($operator.".public");
            if ($operator) {
                $path = $this->stripPrefix($path, $operator);
                return $this->fileExists($path, $operator) ? $this->getPathPrefixer($operator)?->prefixPath($path) : null;
            }
        }

        return null;
    }
}
