<?php

namespace Base\Cache\Warmer;

use Base\Attributes\AnnotationReader;
use Base\Cache\Abstract\AbstractLocalCacheWarmer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class AnnotationCacheWarmer extends AbstractLocalCacheWarmer
{
    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        if (!parent::doWarmUp($cacheDir, $arrayAdapter, $buildDir)) {
            return false;
        }

        // Heavy annotation precompute (every entity class + every routed
        // controller) belongs HERE, at cache:warmup time — not in
        // AnnotationReader's constructor, where the route-collection load
        // could re-enter a Doctrine event dispatch (ClassMetadata race).
        // parent::doWarmUp() already pointed the reader's cache at
        // $arrayAdapter, so the precomputed buckets end up in the dumped
        // PHP-array cache file.
        if ($this->simpleCache instanceof AnnotationReader) {
            $this->simpleCache->precompute();
        }

        return true;
    }
}
