<?php

namespace Base\Service;

use Base\Exception\NotDeletableException;
use Base\Exception\NotReadableException;
use Base\Exception\NotWritableException;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\FlysystemBundle\Lazy\LazyFactory;

class Filesystem
{
    protected string $storage;
    public function __construct(LazyFactory $lazyFactory)
    {
        $this->lazyFactory = $lazyFactory;
    }

    public function setDefault(?string $storage): self { return $this->set($storage); } 
    public function set(?string $storage): self
    {
        $this->storage = $storage;
        return $this;
    }

    public function getOperator(?string $storage = null): FilesystemOperator
    { 
        if(!$storage) $storage = $this->storage;
        if(!$storage) throw new \Exception("Storage passed as argument is NULL, no default storage set.");
        return $this->lazyFactory->createStorage($storage, $storage); 
    }

    public function getAdapter(?string $storage = null): FilesystemAdapter
    {
        if($storage instanceof FilesystemOperator) $fsOperator = $storage;
        else $fsOperator = $this->getOperator($storage); 

        $reflProperty = new \ReflectionProperty(Flysystem::class, 'adapter');
        $reflProperty->setAccessible(true);

        return $reflProperty->getValue($fsOperator);
    }

    public function getPathPrefixer(?string $storage = null): PathPrefixer
    {
        $adapter = $this->getAdapter($storage);

        if($adapter instanceof LocalFilesystemAdapter) {

            $reflProperty = new \ReflectionProperty(LocalFilesystemAdapter::class, 'prefixer');
            $reflProperty->setAccessible(true);

            return $reflProperty->getValue($adapter);
        }
        
        return null;
    }


    public function getHashId(string|null $source, array $filters = [], array $config = []): ?string
    {
        // if($source === null ) return null;
        // $path = "imagine/".str_strip($source, $this->assetExtension->getAssetUrl(""));

        // $config["path"] = $path;
        // $config["options"] = array_merge(["quality" => $this->getMaximumQuality()], $config["options"] ?? []);
        // if(!empty($filters)) $config["filters"] = $filters;

        // while ( ($sourceConfig = $this->decode(basename($source))) ) {

        //     $source = $sourceConfig["path"] ?? $source;
        //     $config["path"] = $source;
        //     $config["filters"] = ($sourceConfig["filters"] ?? []) + ($config["filters"] ?? []);
        //     $config["options"] = ($sourceConfig["options"] ?? []) + ($config["options"] ?? []);
        // }

        // return $this->encode($config);
    }

    public function resolveArguments(string $hashid, array $filters = [], array $args = [])
    {
        // $path = null;
        // $args = [];

        // do {
        
        //     // Path fallback
        //     $args0 = null;
        //     $hashid0 = $hashid;
        //     while(strlen($hashid0) > 1) {

        //         $args0 = $this->decode(basename($hashid0));
        //         if($args0) break;

        //         $hashid0 = dirname($hashid0);
        //     }

        //     if(!is_array($args0)) $path = $hashid;
        //     else {

        //         $hashid = array_pop_key("path", $args0) ?? $hashid;
        //         $filters = array_key_exists("filters", $args0) ? array_merge($args0["filters"], $filters) : $filters;
        //         $args = array_merge($args, $args0);
        //     }

        // } while(is_array($args0));

        // $args["path"]    = $path;
        // $args["filters"] = $filters;
        // $args["options"] = $args["options"] ?? [];
        // return $args;
    }
    
    public function isImage(string $location, ?FilesystemOperator $fsOperator = null) { return preg_match("/image\/\*/", $this->mimeType($location, $fsOperator)); }
    public function read(string $location, ?FilesystemOperator $fsOperator = null): ?string
    {
        if(!$fsOperator) $fsOperator = $this->getOperator();
        if(!$fsOperator->fileExists($location)) return null;

        try { return $fsOperator->read($location); }
        catch (UnableToReadFile $e) { throw new NotReadableException("Unable to read file \"$location\".. ".$e->getMessage()); }
    }

    public function write(string $location, string $contents, ?FilesystemOperator $fsOperator = null, array $config = [])
    {
        if(!$fsOperator) $fsOperator = $this->getOperator();
        if ($fsOperator->fileExists($location)) return false;

        try { $fsOperator->write($location, $contents, $config); }
        catch (UnableToWriteFile $e) { throw new NotWritableException("Unable to write file \"$location\".. ".$e->getMessage()); }
        return true;
    }

    public function delete(string $location, ?FilesystemOperator $fsOperator = null)
    {
        if(!$fsOperator) $fsOperator = $this->getOperator();
        if(!$fsOperator->fileExists($location)) return false;

        try { $fsOperator->delete($location); }
        catch (UnableToDeleteFile|UnableToDeleteDirectory $e) { throw new NotDeletableException("Unable to delete file \"$location\".. ".$e->getMessage()); }
        return true;
    }

    public function fileExists(string $location, ?FilesystemOperator $fsOperator = null): bool 
    {
        if(!$fsOperator) $fsOperator = $this->getOperator();
        return $fsOperator->fileExists($location);
    }

    public function mkdir(string $location, ?FilesystemOperator $fsOperator = null, array $config = []) 
    {
        if(!$fsOperator) $fsOperator = $this->getOperator();
        if($fsOperator->fileExists($location)) return false;

        try { $fsOperator->createDirectory($location, $config); }
        catch (UnableToDeleteFile|UnableToDeleteDirectory $e) { throw new NotDeletableException("Unable to create directory \"$location\".. ".$e->getMessage()); }
        return true;
    }

    public function mimeType(string $location, ?FilesystemOperator $fsOperator = null) 
    {
        if(!$fsOperator) $fsOperator = $this->getOperator();
        if(!$fsOperator->fileExists($location)) return null;

        try { $fsOperator->mimeType($location); }
        catch (UnableToRetrieveMetadata $e) { throw new NotDeletableException("Unable to read mimetype \"$location\".. ".$e->getMessage()); }
        return null;
    }
}