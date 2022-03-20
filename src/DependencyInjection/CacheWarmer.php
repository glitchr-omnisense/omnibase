<?php

namespace Base\DependencyInjection;

use Base\Service\IconService;
use Base\Annotations\AnnotationReader;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class CacheWarmer implements CacheWarmerInterface
{
    public function __construct(IconService $iconService)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
        $this->iconService = $iconService;
    }

    public function isOptional():bool { return false; }
    public function warmUp($cacheDir): array
    {
        if($this->shellVerbosity > 0) echo " // Warming up cache... Base bundle".PHP_EOL.PHP_EOL;

        return [get_class($this->iconService)];
    }
}