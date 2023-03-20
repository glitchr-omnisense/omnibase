<?php

namespace Base\Cache\Abstract;

use Symfony\Component\Cache\Adapter\ArrayAdapter;

interface AbstractLocalCacheWarmerInterface
{
    public function getCache(): ArrayAdapter;
}
