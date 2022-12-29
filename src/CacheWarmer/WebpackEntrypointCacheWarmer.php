<?php

namespace Base\CacheWarmer;

use Base\Cache\SimpleCacheWarmer;
use Base\Twig\Renderer\Adapter\EncoreTagRenderer;

class WebpackEntrypointCacheWarmer extends SimpleCacheWarmer
{
    public function __construct(EncoreTagRenderer $encoreTagRenderer, string $cacheDir, string $publicDir)
    {
        echo ("HEHO");
        exit(1);
        $encoreTagRenderer->addEntrypoint("_base", $publicDir."/bundles/base/entrypoints.json");

        parent::__construct($encoreTagRenderer, $cacheDir);
    }

}
