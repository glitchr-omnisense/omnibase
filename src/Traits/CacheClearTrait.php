<?php

namespace Base\Traits;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;

trait CacheClearTrait
{
    protected function routerFallbackWarning(SymfonyStyle $io): void
    {
        //
        // Router fallback information 
        if($this->parameterBag->get("base.router.fallback_warning") && !$this->router->getHostFallback())
            $io->warning("No host fallback configured in `base.yaml` (configure 'base.router.fallbacks' to remove this message).");
    }
    
    //
    // Check for node_modules directory
    protected function webpackCheck(SymfonyStyle $io): void
    {
        if(class_exists(EntrypointLookupInterface::class) && !is_dir($this->projectDir."/var/modules") && !is_dir($this->projectDir."/node_modules")) {

            $io->error(
                'Node package manager directory `'.$this->projectDir."/node_modules".'` is missing. '.PHP_EOL.
                'Run `npm install` to setup your dependencies !'
            );
        }
    }

    //
    // Run second cache clear command
    protected function doubleCacheClearCheck(SymfonyStyle $io): void
    {
        $testFile = $this->cacheDir."/.test";
        $testFileExists = file_exists($testFile);

        file_put_contents($testFile, "Hello World !");
        if(!$testFileExists)
            $io->warning('Cache requires to run a second `cache:clear` to account for the custom base bundle features.');
    }

    //
    // Disk space and memory checks
    protected function diskAndMemoryCheck(SymfonyStyle $io): void
    {
        $freeSpace = disk_free_space(".");
        $diskSpace = disk_total_space(".");
        $remainingSpace = $diskSpace - $freeSpace;
        $percentSpace = round(100*$remainingSpace/$diskSpace, 2);
        $diskSpaceStr = 'Disk space information: '. byte2str($freeSpace) . ' / ' . byte2str($diskSpace) . " available (".$percentSpace." % used)";

        $memoryLimit = str2dec(ini_get("memory_limit"));
        $memoryLimitStr = $memoryLimit > 1 ? 'PHP Memory limit: ' . byte2str($memoryLimit, array_slice(DECIMAL_PREFIX, 0, 3)) : "";

        if($percentSpace > 95) $fn = "warning";
        else if($percentSpace > 75) $fn = "note";
        else $fn = "info";

        if($memoryLimit > 1 && $memoryLimit < str2dec("512M")) {

            $io->{$fn}($diskSpaceStr);
            $io->warning('Memory limit is very low.. Please consider increasing it'.PHP_EOL.$memoryLimitStr);

        } else {
        
            $io->{$fn}($diskSpaceStr.PHP_EOL.$memoryLimitStr);    
        }
    }

    //
    // PHP config check
    protected function phpConfigCheck(SymfonyStyle $io): void
    {
        $phpConfig = php_ini_loaded_file();
        $maxSize = UploadedFile::getMaxFilesize();
        $io->note("Loaded PHP Configuration: ".$phpConfig." (might difer from webserver)\nMaximum uploadable filesize: ".byte2str($maxSize, BINARY_PREFIX));
    }

    protected function technicalSupportCheck(SymfonyStyle $io): void
    {
        //
        // Technical contact and language
        $technicalRecipient = $this->notifier->getTechnicalRecipient();
        if(is_stringeable($technicalRecipient))
            $io->note("Technical recipient configured: ".$technicalRecipient);
    }

    protected function generateSymlinks(SymfonyStyle $io): void
    {
        //
        // Generate flysystem public symlink
        $storageNames = $this->flysystem->getStorageNames(false);
        if($storageNames)
            $io->note("Flysystem symlink(s) got generated in public directory.");

        foreach($storageNames as $storageName) {

            if(!$this->flysystem->hasStorage($storageName.".public"))
                continue;

            $realPath = str_rstrip($this->flysystem->prefixPath("", $storageName), "/");

            $publicPath = $this->flysystem->getPublicRoot($storageName.".public");
            $publicPath = str_rstrip($publicPath, "/");
            if($realPath == $publicPath)
                continue;

            if(is_link($publicPath) || file_exists($publicPath)) {

                if(is_link($publicPath)) unlink($publicPath);
                else if(is_emptydir($publicPath)) rmdir($publicPath);
                else exit("Public path \"$publicPath\" already exists but it is not a symlink\n");
            }

            symlink($realPath, $publicPath);
        }
    }
}
