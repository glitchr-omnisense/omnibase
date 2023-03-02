<?php

namespace Base\Cache;

use Base\Cache\Abstract\AbstractLocalCacheInterface;
use Psr\SimpleCache\CacheInterface;

interface SimpleCacheInterface extends AbstractLocalCacheInterface, CacheInterface
{
}