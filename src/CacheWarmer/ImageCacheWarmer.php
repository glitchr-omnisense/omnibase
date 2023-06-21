<?php

namespace Base\CacheWarmer;

use Base\BaseBundle;
use Base\Console\CommandExecutorInterface;
use Base\Console\ConsoleInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 *
 */
class ImageCacheWarmer implements CacheWarmerInterface
{
    protected int $shellVerbosity;
    protected ConsoleInterface $console;

    public function __construct(ConsoleInterface $console)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
        $this->console = $console;
    }

    public function isOptional(): bool
    {
        return true;
    }

    /**
     * @param $cacheDir
     * @return array|string[]
     */
    public function warmUp($cacheDir): array
    {
        if ($this->shellVerbosity > 0 && php_sapi_name() == "cli") {
            echo " // Warming up cache... Prepare database image" . PHP_EOL . PHP_EOL;
        }

        $this->console->verbosity($this->shellVerbosity);
        $this->console->exec("uploader:images", ["--warmup"]);

        return [];
    }
}
