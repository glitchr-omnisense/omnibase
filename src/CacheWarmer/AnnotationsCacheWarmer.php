<?php

namespace Base\CacheWarmer;

use Base\Annotations\AnnotationReader;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function is_file;

class AnnotationsCacheWarmer extends AbstractPhpFileCacheWarmer
{
    /** @var string */
    private $phpArrayFile;

    public function __construct(AnnotationReader $annotationReader, string $phpArrayFile)
    {
        $this->annotationReader = $annotationReader;
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");

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

        if($this->shellVerbosity > 0 && php_sapi_name() == "cli")
            echo " // Warming up cache... Annotation warmer".PHP_EOL.PHP_EOL;

        $this->annotationReader->setCache($arrayAdapter);
        return $this->annotationReader->warmUp();
    }
}
