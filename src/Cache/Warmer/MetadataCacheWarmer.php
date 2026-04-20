<?php

namespace Base\Cache\Warmer;

use Base\Annotations\AnnotationReader;
use Base\Cache\Abstract\AbstractLocalCache;
use Base\Cache\Abstract\AbstractLocalCacheInterface;
use Base\Cache\Abstract\AbstractLocalCacheWarmer;
use Base\Database\Mapping\ClassMetadataManipulator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class MetadataCacheWarmer extends AbstractLocalCacheWarmer
{
    protected AnnotationReader $annotationReader;

    public function __construct(AbstractLocalCacheInterface $simpleCache, AnnotationReader $annotationReader, string $cacheDir)
    {
        parent::__construct($simpleCache, $cacheDir);
        $this->annotationReader = $annotationReader;
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        if (!parent::doWarmUp($cacheDir, $arrayAdapter, $buildDir)) {
            return false;
        }

        if ($this->simpleCache instanceof ClassMetadataManipulator) {
            $this->simpleCache->enrichAndSaveCompletors($this->annotationReader);
        }

        return true;
    }
}
