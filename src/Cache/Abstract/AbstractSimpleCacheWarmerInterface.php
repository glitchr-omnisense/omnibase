<?php

namespace Base\Cache\Abstract;

use Symfony\Component\Cache\Adapter\ArrayAdapter;

interface AbstractSimpleCacheWarmerInterface
{
    public function getCache(): ArrayAdapter;
}