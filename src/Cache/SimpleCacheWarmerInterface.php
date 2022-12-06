<?php

namespace Base\Cache;

use Symfony\Component\Cache\Adapter\ArrayAdapter;

interface SimpleCacheWarmerInterface
{
    public function getCache(): ArrayAdapter;
}