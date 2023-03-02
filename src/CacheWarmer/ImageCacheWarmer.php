<?php

namespace Base\CacheWarmer;

use Base\Console\Command\UploaderImagesCommand;
use Base\Console\Command\UploaderImagesCropCommand;
use Base\Console\CommandExecutorInterface;
use Base\Console\ConsoleInterface;
use Base\Entity\Layout\Image;
use Base\Repository\Layout\ImageRepository;
use Base\Service\SettingBag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class ImageCacheWarmer implements CacheWarmerInterface
{
    protected int $shellVerbosity;
    protected $console;

    public function __construct(ConsoleInterface $console)
    {
        $this->shellVerbosity = getenv("SHELL_VERBOSITY");
        $this->console = $console;
    }

    public function isOptional() : bool { return true; }
    public function warmUp($cacheDir): array
    {
        if($this->shellVerbosity > 0 && php_sapi_name() == "cli")
            echo " // Warming up cache... Prepare database image".PHP_EOL.PHP_EOL;

        $this->console->verbosity($this->shellVerbosity);
        $this->console->exec("uploader:images", ["--warmup"]);

        return [];
    }
}