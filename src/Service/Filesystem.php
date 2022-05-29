<?php

namespace Base\Service;

use Base\Exception\NotDeletableException;
use Base\Exception\NotReadableException;
use Base\Exception\NotWritableException;
use League\Flysystem\CorruptedPathDetected;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\FlysystemBundle\Lazy\LazyFactory;
use ReflectionException;
use Symfony\Component\Finder\Finder;

class Filesystem
{
    /**
     * @var FilesystemOperator
     */
    protected FilesystemOperator $operator;

    /**
     * @var LazyFactory
     */
    protected LazyFactory $lazyFactory;

    protected static $projectDir;
    public static function getProjectDir() { return self::$projectDir; }

    protected static $publicDir;
    public static function getPublicDir() { return self::$publicDir; }

    public function __construct(LazyFactory $lazyFactory)
    {
        $this->lazyFactory = $lazyFactory;

        self::$projectDir = dirname(__FILE__, 6);
        self::$publicDir  = self::$projectDir."/public";

        $this->setDefault("local.storage");
    }

    public function getDefault() { return $this->getOperator(); }
    public function setDefault(FilesystemOperator|string $operator): self
    {
        $this->operator = $this->getOperator($operator);
        return $this;
    }

    public function getOperator(FilesystemOperator|string|null $operator = null): FilesystemOperator
    {
        if (class_implements_interface($operator, FilesystemOperator::class))
            return $operator;

        if (is_string($operator))
            return $this->lazyFactory->createStorage($operator, $operator);

        return $this->operator;
    }

    public function getAdapter(FilesystemOperator|string|null $operator = null): FilesystemAdapter
    {
        $operator = $this->getOperator($operator);

        try { $reflProperty = new \ReflectionProperty(get_class($operator), 'adapter'); }
        catch (ReflectionException $e) { return null; }

        $reflProperty->setAccessible(true);
        return $reflProperty->getValue($operator);
    }

    protected function getPathPrefixer(FilesystemOperator|string|null $operator = null): PathPrefixer
    {
        $adapter = $this->getAdapter($operator);

        try { $reflProperty = new \ReflectionProperty(get_class($adapter), 'prefixer'); }
        catch (ReflectionException $e) { return null; }

        $reflProperty->setAccessible(true);
        return $reflProperty->getValue($adapter);
    }

    public function prefixPath(string $path, FilesystemOperator|string|null $operator = null)
    {
        $prefixPath = $this->getPathPrefixer($operator)->prefixPath("");
        return $prefixPath.str_lstrip($path, $prefixPath);
    }

    public function stripPrefix(string $path, FilesystemOperator|string|null $operator = null)
    {
        $prefixPath = $this->getPathPrefixer($operator)->prefixPath("");
        return str_lstrip($path, $prefixPath);
    }

    public function read(string $path, FilesystemOperator|string|null $operator = null): ?string
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if(!$this->fileExists($path, $operator)) return null;

        try { return $operator->read($path); }
        catch (UnableToReadFile $e) { throw new NotReadableException("Unable to read file \"$path\".. ".$e->getMessage()); }
    }

    public function write(string $path, string $contents, FilesystemOperator|string|null $operator = null, array $config = [])
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if ($this->fileExists($path, $operator)) return false;

        try { $operator->write($path, $contents, $config); }
        catch (UnableToWriteFile $e) { throw new NotWritableException("Unable to write file \"$path\".. ".$e->getMessage()); }
        return true;
    }

    public function delete(string $path, FilesystemOperator|string|null $operator = null)
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if(!$this->fileExists($path, $operator)) return false;

        try { $operator->delete($path); }
        catch (UnableToDeleteFile|UnableToDeleteDirectory $e) { throw new NotDeletableException("Unable to delete file \"$path\".. ".$e->getMessage()); }
        return true;
    }

    public function fileExists(string $path, FilesystemOperator|string|null $operator = null): bool
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        try { return $operator->fileExists($path); }
        catch (CorruptedPathDetected $e ) { return false; }
    }

    public function mkdir(string $path, FilesystemOperator|string|null $operator = null, array $config = [])
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if($this->fileExists($path, $operator)) return false;

        try { $operator->createDirectory($path, $config); }
        catch (UnableToDeleteFile|UnableToDeleteDirectory $e) { throw new NotDeletableException("Unable to create directory \"$path\".. ".$e->getMessage()); }
        return true;
    }

    public function mimeType(string $path, FilesystemOperator|string|null $operator = null) : ?string
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if(!$this->fileExists($path, $operator)) return null;

        try { return $operator->mimeType($path); }
        catch (UnableToRetrieveMetadata $e) { throw new NotDeletableException("Unable to read mimetype \"$path\".. ".$e->getMessage()); }
        return null;
    }

    public function get(mixed $path, FilesystemOperator|string|null $operator = null)
    {
        $operator = $this->getOperator($operator);
        $path = $this->stripPrefix($path, $operator);

        if($this->fileExists($path, $operator))
            return $this->getPathPrefixer($operator)->prefixPath($path);

        return null;
    }

    public function getPublic(mixed $path, FilesystemOperator|string|null $operator = null)
    {
        if($path === null) return null;

        $path = $this->stripPrefix($path, $operator);
        $path = $this->getPathPrefixer($operator)->prefixPath($path);
        if(in_array($path, ["", "/"])) return $this->getPublicDir();

        //
        // Check if file is reacheable in /public directory
        $operator = $this->getOperator($operator);
        if($operator) {

            $endpoints = $this->getPublicRealpath();
            foreach($endpoints as $alias => $realpath) {

                if(str_starts_with($path, $realpath) && file_exists($alias.str_lstrip($path, $realpath)))
                    return $alias.str_lstrip($path, $realpath);
            }
        }

        //
        // Check if the corresponding public operator is found
        if(is_string($operator)) {

            $operator = $this->getOperator($operator.".public");
            if($operator) {

                $path = $this->stripPrefix($path, $operator);
                return $this->fileExists($path, $operator) ? $this->getPathPrefixer($operator)->prefixPath($path) : null;
            }
        }

        return null;
    }

    protected function getPublicRealpath(?string $path = null, int $depth = 1): array {

        $publicPath = realpath($this->getPublicDir()."/".str_lstrip($path, [$this->getPublicDir(), "/"]));

        $endpoints = [$publicPath => realpath($publicPath)];
        foreach(Finder::create()->followLinks()->directories()->in($publicPath)->depth("< ".$depth) as $path)
            $endpoints[$path->getPathname()] = realpath($path->getPathname());

        return $endpoints;
    }
}