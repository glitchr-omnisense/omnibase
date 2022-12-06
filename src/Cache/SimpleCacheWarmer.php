<?php

namespace Base\Cache;

use Base\BaseBundle;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

abstract class SimpleCacheWarmer extends AbstractPhpFileCacheWarmer implements SimpleCacheWarmerInterface
{
    /** @var string */
    private $cacheFile;

    public function __construct(SimpleCacheInterface $simpleCache, string $cacheDir)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");

        $this->simpleCache = $simpleCache;

        $this->cacheFile = $cacheDir."/simple_cache/".str_replace(['\\', '/'], ['__', '_'], get_class($simpleCache)).".php";
        parent::__construct($this->cacheFile);
    }

    public function getCache(): ArrayAdapter { return $this->arrayAdapter; }
    public function isOptional(): bool { return false; }
    
    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter): bool
    {
        if(!BaseBundle::CACHE) return false;
     
        if (is_file($this->cacheFile))
            return false;

        if($this->shellVerbosity > 0 && php_sapi_name() == "cli")
            echo " // Warming up cache... " . ucwords(camel2snake(str_replace("CacheWarmer", "", class_basename(static::class)), " ")) . PHP_EOL.PHP_EOL;

        $this->simpleCache->setCache($arrayAdapter);
        return $this->simpleCache->warmUp($cacheDir);
    }
}