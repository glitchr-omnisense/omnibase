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
        if($storage instanceof FilesystemOperator) $filesystemOperator = $storage;
        else $filesystemOperator = $this->getOperator($storage); 

        $reflProperty = new \ReflectionProperty(Flysystem::class, 'adapter');
        $reflProperty->setAccessible(true);

        return $reflProperty->getValue($filesystemOperator);
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

    public function read(string $location, ?FilesystemOperator $filesystemOperator = null): ?string
    {
        if(!$filesystemOperator) $filesystemOperator = $this->getOperator();
        if(!$filesystemOperator->fileExists($location)) return null;

        try { return $filesystemOperator->read($location); }
        catch (UnableToReadFile $e) { throw new NotReadableException("Unable to read file \"$location\""); }
    }

    public function write(string $location, string $contents, ?FilesystemOperator $filesystemOperator = null, array $config = [])
    {
        if(!$filesystemOperator) $filesystemOperator = $this->getOperator();
        if ($filesystemOperator->fileExists($location)) return false;

        try { $filesystemOperator->write($location, $contents, $config); }
        catch (UnableToWriteFile $e) { throw new NotWritableException("Unable to write file \"$location\".."); }
        return true;
    }

    public function delete(string $location, ?FilesystemOperator $filesystemOperator = null)
    {
        if(!$filesystemOperator) $filesystemOperator = $this->getOperator();
        if(!$filesystemOperator->fileExists($location)) return false;

        try { $filesystemOperator->delete($location); }
        catch (UnableToDeleteFile|UnableToDeleteDirectory $e) { throw new NotDeletableException("Unable to delete file \"$location\".."); }
        return true;
    }

    public function fileExists(string $location, ?FilesystemOperator $filesystemOperator = null): bool 
    {
        if(!$filesystemOperator) $filesystemOperator = $this->getOperator();
        return $filesystemOperator->fileExists($location);
    }

    public function mkdir(string $location, ?FilesystemOperator $filesystemOperator = null, array $config = []) 
    {
        if(!$filesystemOperator) $filesystemOperator = $this->getOperator();
        if($filesystemOperator->fileExists($location)) return false;

        try { $filesystemOperator->createDirectory($location, $config); }
        catch (UnableToDeleteFile|UnableToDeleteDirectory $e) { throw new NotDeletableException("Unable to create directory \"$location\".."); }
        return true;
    }

    public function mimeType(string $location, ?FilesystemOperator $filesystemOperator = null) 
    {
        if(!$filesystemOperator) $filesystemOperator = $this->getOperator();
        if(!$filesystemOperator->fileExists($location)) return null;

        try { $filesystemOperator->mimeType($location); }
        catch (UnableToRetrieveMetadata $e) { throw new NotDeletableException("Unable to read mimetype \"$location\".."); }
        return null;
    }
}