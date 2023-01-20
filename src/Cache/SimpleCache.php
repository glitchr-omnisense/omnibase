<?php

namespace Base\Cache;

use Base\Cache\Abstract\AbstractSimpleCache;

class SimpleCache extends AbstractSimpleCache
{
    public function warmUp(string $cacheDir): array { }
}