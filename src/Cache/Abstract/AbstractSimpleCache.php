<?php

namespace Base\Cache\Abstract;

use Psr\Cache\CacheItemPoolInterface;
use Base\Traits\SimpleCacheTrait;

abstract class AbstractSimpleCache implements AbstractSimpleCacheInterface
{
    private bool $saveDeferred = false;
    private ?CacheItemPoolInterface $cache = null;
    use SimpleCacheTrait;
}