<?php

namespace Base\Service;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathPrefixer;

use League\FlysystemBundle\Lazy\LazyFactory;

class FilesystemProvider
{
    public function __construct(LazyFactory $lazyFactory)
    {
        $this->lazyFactory = $lazyFactory;
    }

    public function get(string $storage) { return $this->lazyFactory->createStorage($storage, $storage); }

    public function getAdapter($storage): FilesystemAdapter
    {
        if($storage instanceof FilesystemOperator)
            $filesystem = $storage;

        if(is_string($storage))
            $filesystem = $this->get($storage); 

        $reflectionProperty = new \ReflectionProperty(Filesystem::class, 'adapter');
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($filesystem);
    }

    public function getPathPrefixer($storage): PathPrefixer
    {
        $adapter = $this->getAdapter($storage);
        if($adapter instanceof LocalFilesystemAdapter) {

            $reflectionProperty = new \ReflectionProperty(LocalFilesystemAdapter::class, 'prefixer');
            $reflectionProperty->setAccessible(true);

            return $reflectionProperty->getValue($adapter);
        }
        
        return null;
    }

}