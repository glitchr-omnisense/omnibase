<?php

namespace Base\Service;

use Base\Exception\NotDeletableException;
use Base\Exception\NotReadableException;
use Base\Exception\NotWritableException;

use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\PathPrefixer;

use LogicException;
use Omnidrive\Drive;
use Omnidrive\Drives;
use Omnidrive\Exception\DriveException;
use Omnidrive\Flysystem\FlysystemDrive;
use Omnidrive\Flysystem\OperatorDrives;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;
use InvalidArgumentException;
use League\Flysystem\UnableToCreateDirectory;
use ReflectionException;

/**
 * omnibase's storages, by name ("local.cache", "local.uploads", "s3.private"...), read and written
 * through omniscient/omnidrive (plan "omnidrive", 2026-10-23): each storage is an Omnidrive\Drive
 * of the Omnidrive\Drives the host gives - under Symfony the storages of flysystem-bundle's
 * configuration (the services tagged flysystem.storage), or omnibase's local ones without the bundle
 * (FlysystemStoragesPass); under Laravel its disks (omnibridge/laravel). A PSR-11 container of
 * Flysystem operators is still accepted, read as Omnidrive\Flysystem\OperatorDrives.
 *
 * New code type-hints Omnidrive\Drives (getDrives(), getDrive()). What speaks Flysystem itself -
 * getOperator(), getAdapter(), getConnectionOptions() and the FilesystemOperator arguments - is
 * kept for one release, deprecated (FlysystemInterface).
 */
class Flysystem implements FlysystemInterface, Storages
{
    protected Drives $drives;

    /** The default storage: its name, or an operator given as it (deprecated). */
    protected FilesystemOperator|string $default = "local.cache";

    protected static string $projectDir;

    /**
     * @return string
     */
    public static function getProjectDir()
    {
        return self::$projectDir;
    }

    protected static string $publicDir;

    /**
     * @return string
     */
    public static function getPublicDir()
    {
        return self::$publicDir;
    }

    public function __construct(Drives|ContainerInterface $storages)
    {
        $this->drives = $storages instanceof Drives ? $storages : new OperatorDrives($storages);

        self::$projectDir = dirname(__FILE__, 6);
        self::$publicDir = self::$projectDir . "/public";

        if (!$this->hasStorage("local.cache")) {
            throw new InvalidArgumentException("\"local.cache\" storage not found in your Flysystem configuration.");
        }

        $this->setDefaultStorage("local.cache");
    }

    /** The storages, as omnidrive's drives. */
    public function getDrives(): Drives
    {
        return $this->drives;
    }

    /** A storage as an omnidrive drive: by its name, the default one when null. */
    public function getDrive(FilesystemOperator|string|null $storage = null): Drive
    {
        $storage ??= $this->default;
        if (is_string($storage)) {
            if (!$this->hasStorage($storage)) {
                throw new InvalidArgumentException("\"" . $storage . "\" storage not found in your Flysystem configuration.");
            }

            return $this->drives->get($storage);
        }

        // An operator given as it (deprecated): a drive over it, under no name of the host's.
        return FlysystemDrive::of('operator', $storage);
    }

    /**
     * @deprecated since omnibase 4.0, the operator behind a drive: getDrive()
     */
    public function createStorage(string $source, ?string $storageName = null): FilesystemOperator
    {
        if (!$this->hasStorage($source)) {
            throw new InvalidArgumentException('You have requested a non-existent source storage "' . $source . '" in lazy storage "' . ($storageName ?? $source) . '".');
        }

        return $this->operatorOf($this->drives->get($source));
    }

    public function hasStorage(string $storageName): bool
    {
        return $this->drives->has($storageName);
    }

    public function getStorageNames(bool $public = true): array
    {
        // The names the host can list (a service locator's, Laravel's disks): a plain container lists none.
        return array_filter(
            $this->drives->names(),
            fn($s) => $public || !str_ends_with($s, ".public"),
        );
    }

    /**
     * @deprecated since omnibase 4.0, the default drive: getDrive()
     */
    public function getDefaultStorage(): FilesystemOperator
    {
        return $this->getOperator($this->default);
    }

    /**
     * @param FilesystemOperator|string $operator
     * @return $this
     */
    public function setDefaultStorage(FilesystemOperator|string $operator)
    {
        if (is_string($operator) && !$this->hasStorage($operator)) {
            throw new InvalidArgumentException("\"" . $operator . "\" storage not found in your Flysystem configuration.");
        }
        $this->default = $operator;
        return $this;
    }

    /**
     * @deprecated since omnibase 4.0, Flysystem's operator behind a storage: getDrive() reads and writes it
     */
    public function getOperator(FilesystemOperator|string|null $operator = null): FilesystemOperator
    {
        if ($operator instanceof FilesystemOperator) {
            return $operator;
        }

        return $this->operatorOf($this->getDrive($operator));
    }

    private function operatorOf(Drive $drive): FilesystemOperator
    {
        return $drive instanceof FlysystemDrive ? $drive->operator() : throw new LogicException(sprintf("The \"%s\" storage is a %s: no Flysystem operator behind it.", $drive->name(), get_debug_type($drive)));
    }

    /**
     * @deprecated since omnibase 4.0, Flysystem's adapter behind a storage
     */
    public function getAdapter(FilesystemOperator|string|null $operator = null): FilesystemAdapter
    {
        $operator = $this->getOperator($operator);

        $reflProperty = new ReflectionProperty(get_class($operator), 'adapter');
        $reflProperty->setAccessible(true);

        return $reflProperty->getValue($operator);
    }

    protected function getPathPrefixer(FilesystemOperator|string|null $operator = null): PathPrefixer
    {
        $adapter = $this->getAdapter($operator);

        //
        // Prefixer
        $reflProperty = new ReflectionProperty(get_class($adapter), 'prefixer');
        $reflProperty->setAccessible(true);

        return $reflProperty->isInitialized($adapter) ? $reflProperty->getValue($adapter) : new PathPrefixer($this->getConnectionOptions($operator)["root"] ?? "/");
    }

    public function getConnectionOptions(FilesystemOperator|string|null $operator = null): ?array
    {
        $adapter = $this->getAdapter($operator);

        //
        // Connection options
        try {
            $reflProperty = new ReflectionProperty(get_class($adapter), 'connectionOptions');
        } catch (ReflectionException $e) {
            // Connection provider (SFTP)
            try {
                $reflProperty = new ReflectionProperty(get_class($adapter), 'connectionProvider');
            } catch (ReflectionException $e) {
                return null;
            }
        }

        $reflProperty->setAccessible(true);
        return $reflProperty->isInitialized($adapter) ? to_array($reflProperty->getValue($adapter)) : null;
    }

    /**
     * @param string $path
     * @param FilesystemOperator|string|null $operator
     * @return string
     */
    public function prefixPath(string $path, FilesystemOperator|string|null $operator = null)
    {
        $prefixPath = $this->getPathPrefixer($operator)?->prefixPath("") ?? "";
        return $prefixPath . str_lstrip($path, $prefixPath);
    }

    /**
     * @param string $path
     * @param FilesystemOperator|string|null $operator
     * @return string|null
     */
    public function stripPrefix(string $path, FilesystemOperator|string|null $operator = null)
    {
        $prefixPath = $this->getPathPrefixer($operator)?->prefixPath("") ?? "";
        return str_lstrip($path, $prefixPath);
    }

    public function read(string $path, FilesystemOperator|string|null $operator = null): ?string
    {
        $drive = $this->getDrive($operator);
        $path = $this->stripPrefix($path, $operator);

        if (!$this->fileExists($path, $operator)) {
            return null;
        }

        try {
            return $drive->read($path);
        } catch (DriveException $e) {
            throw new NotReadableException("Unable to read file \"$path\".. " . $e->getMessage());
        }
    }

    public function write(string $path, string $contents, FilesystemOperator|string|null $operator = null, array $config = []): bool
    {
        $drive = $this->getDrive($operator);
        $path = $this->stripPrefix($path, $operator);

        if ($this->fileExists($path, $operator)) {
            return false;
        }

        try {
            $drive->write($path, $contents, $config);
        } catch (DriveException $e) {
            throw new NotWritableException("Unable to write file \"$path\".. " . $e->getMessage());
        }
        return true;
    }

    public function delete(string $path, FilesystemOperator|string|null $operator = null): bool
    {
        $drive = $this->getDrive($operator);
        $path = $this->stripPrefix($path, $operator);

        if (!$this->fileExists($path, $operator)) {
            return false;
        }

        try {
            $drive->delete($path);
        } catch (DriveException $e) {
            throw new NotDeletableException("Unable to delete file \"$path\".. " . $e->getMessage());
        }
        return true;
    }

    public function fileExists(string $path, FilesystemOperator|string|null $operator = null): bool
    {
        $drive = $this->getDrive($operator);
        $path = $this->stripPrefix($path, $operator);

        try {
            return $drive->exists($path);
        } catch (DriveException $e) {
            if ($e->getPrevious() instanceof CorruptedPathDetected) {
                return false;
            }
            throw $e;
        }
    }

    public function mkdir(string $path, FilesystemOperator|string|null $operator = null, array $config = []): bool
    {
        $drive = $this->getDrive($operator);
        $path = $this->stripPrefix($path, $operator);

        if ($this->fileExists($path, $operator)) {
            return false;
        }

        try {
            $drive->createDirectory($path, $config);
        } catch (DriveException $e) {
        }
        return true;
    }

    public function mimeType(string $path, FilesystemOperator|string|null $operator = null): ?string
    {
        $drive = $this->getDrive($operator);
        $path = $this->stripPrefix($path, $operator);

        if (!$this->fileExists($path, $operator)) {
            return null;
        }

        try {
            return $drive->mimeType($path);
        } catch (DriveException $e) {
            throw new NotReadableException("Unable to read mimetype \"$path\".. " . $e->getMessage());
        }
    }

    /**
     * @param mixed $path
     * @param FilesystemOperator|string|null $operator
     * @return string|null
     */
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
        $publicPath = realpath($this->getPublicDir() . "/" . str_lstrip($path, [$this->getPublicDir(), "/"]));

        $endpoints = [$publicPath => realpath($publicPath)];
        foreach (Finder::create()->followLinks()->directories()->in($publicPath)->depth("< " . $depth) as $path) {
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

    /**
     * @param FilesystemOperator|string|null $operator
     * @return bool
     */
    public function isRemote(FilesystemOperator|string|null $operator = null)
    {
        // Anything that has no file of this machine behind its paths (S3/MinIO, SFTP, FTP...):
        // what the drive says of itself.
        return !$this->getDrive($operator)->capabilities()->local;
    }

    /**
     * @param mixed $path
     * @param FilesystemOperator|string|null $operator
     * @return string|null
     */
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
        $endpoints = $this->getPublicRealpath();
        foreach ($endpoints as $alias => $realpath) {
            if (str_starts_with($path, $realpath) && file_exists($alias . str_lstrip($path, $realpath))) {
                return $alias . str_lstrip($path, $realpath);
            }
        }

        //
        // Check if the corresponding public operator is found

        return null;
    }
}
