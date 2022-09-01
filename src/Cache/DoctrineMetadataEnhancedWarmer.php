<?php

namespace Base\Cache;

use Base\Annotations\AnnotationReader;
use Base\Database\Mapping\ClassMetadataCompletor;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function is_file;

class DoctrineMetadataEnhancedWarmer extends AbstractPhpFileCacheWarmer
{
    /** @var string */
    private $phpArrayFile;

    public function __construct(ClassMetadataCompletor $classMetadataCompletor, string $phpArrayFile)
    {
        $this->classMetadataCompletor = $classMetadataCompletor;

        $this->phpArrayFile  = $phpArrayFile;
        parent::__construct($phpArrayFile);
    }

    /**
     * It must not be optional because it should be called before ProxyCacheWarmer which is not optional.
     */
    public function isOptional(): bool
    {
        return false;
    }

    /** @param string $cacheDir */
    protected function doWarmUp($cacheDir, ArrayAdapter $arrayAdapter): bool
    {
        if (is_file($this->phpArrayFile)) {
            return false;
        }

        $this->classMetadataCompletor->setCache($arrayAdapter);
        return $this->classMetadataCompletor->warmUp();
    }
}
