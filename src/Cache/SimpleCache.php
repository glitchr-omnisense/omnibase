<?php

namespace Base\Cache;

use Base\Cache\Abstract\AbstractSimpleCache;

final class SimpleCache extends AbstractSimpleCache
{
    public function warmUp(string $cacheDir): array { return []; }
}