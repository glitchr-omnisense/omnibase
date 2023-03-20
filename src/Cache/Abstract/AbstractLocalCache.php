<?php

namespace Base\Cache\Abstract;

use Psr\Cache\CacheItemPoolInterface;
use Base\Traits\SimpleCacheTrait;

abstract class AbstractLocalCache implements AbstractLocalCacheInterface
{
    use SimpleCacheTrait;
    private bool $saveDeferred = false;
    private ?CacheItemPoolInterface $cache = null;
}
