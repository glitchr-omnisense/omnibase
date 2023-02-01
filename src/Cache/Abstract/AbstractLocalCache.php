<?php

namespace Base\Cache\Abstract;

use Psr\Cache\CacheItemPoolInterface;
use Base\Traits\SimpleCacheTrait;

abstract class AbstractLocalCache implements AbstractLocalCacheInterface
{
    private bool $saveDeferred = false;
    private ?CacheItemPoolInterface $cache = null;
    use SimpleCacheTrait;
}