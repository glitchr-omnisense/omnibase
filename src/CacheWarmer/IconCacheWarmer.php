<?php

namespace Base\CacheWarmer;

use Base\Cache\SimpleCacheInterface;
use Base\Cache\SimpleCacheWarmer;
use Base\Service\IconProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class IconCacheWarmer extends SimpleCacheWarmer {

    /**
     * @var array[SimpleCacheInterface]
     */
    protected $adapters;

    public function __construct(IconProvider $iconProvider, string $cacheDir)
    {
        $this->adapters = array_filter($iconProvider->getAdapters(), fn($a) => $a instanceof SimpleCacheInterface);
        parent::__construct($iconProvider, $cacheDir);
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter): bool
    {
        $ret = parent::doWarmUp($cacheDir, $arrayAdapter);

        foreach($this->adapters as $adapter) {

            $adapter->setCache($arrayAdapter);
            $ret &= $adapter->warmUp($cacheDir);
        }

        return $ret;
    }
}