<?php

namespace Base\CacheWarmer;

use Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookup;
use Symfony\WebpackEncoreBundle\Exception\EntrypointNotFoundException;

class WebpackEntrypointCacheWarmer extends AbstractPhpFileCacheWarmer
{
    private $cacheKeys;

    public function __construct(string $phpArrayFile, string $publicDir)
    {
        parent::__construct($phpArrayFile);
        $this->cacheKeys = ["_base" => $publicDir."/bundles/base/entrypoints.json"];

        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
    }


    /**
     * {@inheritdoc}
     */
    protected function doWarmUp($cacheDir, ArrayAdapter $arrayAdapter): bool
    {
        if($this->shellVerbosity > 0 && php_sapi_name() == "cli")
        echo " // Warming up cache... Webpack entry points (".implode(", ", $this->cacheKeys).")".PHP_EOL.PHP_EOL;

        foreach ($this->cacheKeys as $cacheKey => $path) {
          
            // If the file does not exist then just skip past this entry point.
            if (!file_exists($path)) {
                continue;
            }

            $entryPointLookup = new EntrypointLookup($path, $arrayAdapter, $cacheKey);

            try {
                $entryPointLookup->getJavaScriptFiles('dummy');
            } catch (EntrypointNotFoundException $e) {
                // ignore exception
            }
        }

        return true;
    }
}
