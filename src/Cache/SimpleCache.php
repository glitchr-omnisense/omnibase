<?php

namespace Base\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Base\Traits\SimpleCacheTrait;

abstract class SimpleCache implements SimpleCacheInterface
{
    private bool $saveDeferred = false;
    private ?CacheItemPoolInterface $cache = null;
    use SimpleCacheTrait;
}