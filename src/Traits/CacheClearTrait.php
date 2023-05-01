<?php

namespace Base\Traits;

use Base\Database\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;

/**
 *
 */
trait CacheClearTrait
{
    protected function checkCache(SymfonyStyle $io): void
    {
        $io->write("<info> [INFO] Cache directory:</info> " . $this->cacheDir . PHP_EOL, true);
    }

    protected function checkExtensions(SymfonyStyle $io): void
    {
        $Xdebug = extension_loaded('xdebug') ? '<info>✓</info>' : '<error>✗</error>';
        $Blackfire = extension_loaded('blackfire') ? '<info>✓</info>' : '<error>✗</error>';
        $APCu = extension_loaded('apc') && ini_get('apc.enabled') ? '<info>✓</info>' : '<error>✗</error>';
        $OPcache = extension_loaded('Zend OPcache') ? '<info>✓</info>' : '<error>✗</error>';

        $io->write("<info> [INFO] PHP Extensions:</info> (cli and webserver extensions might differ)", true);
        $io->write("        [" . $Xdebug . "] Xdebug; ");
        $io->write("        [" . $APCu . "] APCu", true);
        $io->write("        [" . $Blackfire . "] Blackfire; ");
        $io->write("     [" . $OPcache . "] OPcache", true);
    }

    protected function customFeatureWarnings(SymfonyStyle $io): void
    {
        $useCustomRouter = $this->parameterBag->get("base.router.use_custom");
        $useMailer = $this->parameterBag->get("base.notifier.mailer");
        $useCustomLoader = $this->parameterBag->get("base.twig.use_custom");
        $useCustomReader = $this->parameterBag->get("base.annotations.use_custom");
        $useSettingBag = $this->parameterBag->get("base.parameter_bag.use_setting_bag");
        $useHotParameterBag = $this->parameterBag->get("base.parameter_bag.use_hot_bag");
        $useCustomDbFeatures = $this->parameterBag->get("base.database.use_custom");

        //
        // Router fallback information
        if ($useCustomRouter === null) {
            $io->warning("Advanced router option is not configured in `base.yaml`" . PHP_EOL . "(configure 'base.router.use_custom' boolean to remove this message).");
        } elseif ($useCustomRouter) {
            $io->write(" - Advanced router option is <info>'base.router.use_custom'</info> is SET.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        } else {
            $io->write(" - Advanced router option is <info>'base.router.use_custom'</info> is NOT set.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        }

        if ($useCustomRouter === true) {
            if ($this->parameterBag->get("base.router.fallback_warning") && !$this->router->getHostFallback()) {
                $io->warning("No host fallback configured in `base.yaml`" . PHP_EOL . "(configure 'base.router.fallbacks' to remove this message or disable `base.router.fallback_warning` warning).");
            }
        }

        if ($this->parameterBag->get("base.database.fallback_warning") && !$this->entityManager->getMetadataFactory() instanceof ClassMetadataFactory) {
            $io->warning("Custom ClassMetadataFactory is configured. No fallback configured in `base.yaml`" . PHP_EOL . "(configure 'doctrine.orm.class_metadata_factory_name' to remove this message or disable `base.database.fallback_warning` warning).");
        }

        //
        // Twig custom loader
        if ($useCustomLoader === null) {
            $io->warning("Advanced twig loader option is not configured in `base.yaml`" . PHP_EOL . "(configure 'base.twig.use_custom' boolean to remove this message).");
        } elseif ($useCustomLoader) {
            $io->write(" - Advanced twig loader <info>'base.twig.use_custom'</info> is SET.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        } else {
            $io->write(" - Advanced twig loader <info>'base.twig.use_custom'</info> is NOT set.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        }

        //
        // Annotation reader
        if ($useCustomReader === null) {
            $io->warning("Advanced annotation reader option is not configured in `base.yaml`" . PHP_EOL . "(configure 'base.annotations.use_custom' boolean to remove this message).");
        } elseif ($useCustomReader) {
            $io->write(" - Advanced annotation reader option <info>'base.annotations.use_custom'</info> is SET.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        } else {
            $io->write(" - Advanced annotation reader option <info>'base.annotations.use_custom'</info> is NOT set.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        }

        //
        // Setting bag
        if ($useSettingBag === null) {
            $io->warning("Setting bag is not configured in `base.yaml`" . PHP_EOL . "(configure 'base.parameter_bag.use_setting_bag' boolean to remove this message).");
        } elseif ($useSettingBag) {
            $io->write(" - Setting bag <info>'base.parameter_bag.use_setting_bag'</info> is SET.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        } else {
            $io->write(" - Setting bag <info>'base.parameter_bag.use_setting_bag'</info> is NOT set.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        }

        if ($useMailer === null) {
            $io->warning("Mailer is disabled in `base.yaml`" . PHP_EOL . "(configure 'base.notifier.mailer' boolean to remove this message).");
        } elseif ($useMailer) {
            $io->write(" - Mailer <info>'base.notifier.mailer'</info> is SET.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        } else {
            $io->write(" - Mailer <info>'base.notifier.mailer'</info> is NOT set.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        }

        //
        // Hot parameter bag
        if ($useHotParameterBag === null) {
            $io->warning("Hot parameter feature is not configured in `base.yaml`" . PHP_EOL . "(configure 'base.parameter_bag.use_hot_bag' boolean to remove this message).");
        } elseif ($useHotParameterBag) {
            $io->write(" - Hot parameter bag option <info>'base.parameter_bag_use_hot_bag'</info> is SET.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        } else {
            $io->write(" - Hot parameter bag option <info>'base.parameter_bag_use_hot_bag'</info> is NOT set.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        }

        //
        // Doctrine features
        if ($useCustomDbFeatures === null) {
            $io->warning("Custom DB features are not configured in `base.yaml`" . PHP_EOL . "(configure 'base.database.use_custom' boolean to remove this message).");
        } elseif ($useCustomDbFeatures) {
            $io->write(" - Custom DB features <info>'base.database.use_custom'</info> is SET.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        } else {
            $io->write(" - Custom DB features <info>'base.database.use_custom'</info> is NOT set.", true, SymfonyStyle::VERBOSITY_VERBOSE);
        }

        $io->write("", true, SymfonyStyle::VERBOSITY_VERBOSE);
    }

    //
    // Check for node_modules directory
    protected function webpackCheck(SymfonyStyle $io): void
    {
        if (class_exists(EntrypointLookupInterface::class) && !is_dir($this->projectDir . "/var/modules") && !is_dir($this->projectDir . "/node_modules")) {
            $io->error(
                'Node package manager directory `' . $this->projectDir . "/node_modules" . '` is missing. ' . PHP_EOL .
                'Run `npm install` to setup your dependencies !'
            );
        }
    }

    //
    // Run second cache clear command
    protected function doubleCacheClearCheck(SymfonyStyle $io): void
    {
        file_put_contents($this->testFile, "Hello World !");
        if (!$this->testFileExists) {
            $io->warning('Cache requires to run a second `cache:clear` to account for custom bundle features.');
        }
    }

    //
    // Disk space and memory checks
    protected function diskAndMemoryCheck(SymfonyStyle $io): void
    {
        $freeSpace = disk_free_space(".");
        $diskSpace = disk_total_space(".");
        $remainingSpace = $diskSpace - $freeSpace;
        $percentSpace = round(100 * $remainingSpace / $diskSpace, 2);
        $diskSpaceStr = byte2str($freeSpace) . ' / ' . byte2str($diskSpace) . " available (" . $percentSpace . " % used)";

        $memoryLimit = str2dec(ini_get("memory_limit"));
        $memoryLimitStr = $memoryLimit > 1 ? byte2str($memoryLimit, array_slice(DECIMAL_PREFIX, 0, 3)) : "";

        if ($percentSpace > 95) {
            $fn = "error";
        } elseif ($percentSpace > 75) {
            $fn = "warning";
        } else {
            $fn = "info";
        }

        $io->write("<$fn> [" . strtoupper($fn) . "] Disk space information: </$fn>" . $diskSpaceStr . PHP_EOL, true);
        if ($memoryLimit > 1) {
            if ($memoryLimit < str2dec("512M")) {
                $io->write("<warning> [WARNING] </warning> Memory limit is very low.. Please consider increasing it", true);
                $io->write('PHP Memory limit: ' . $memoryLimitStr);
            } else {
                $io->write("<$fn> [" . strtoupper($fn) . "] PHP Memory limit: </$fn>" . $memoryLimitStr . PHP_EOL, true);
            }
        }
    }

    //
    // PHP config check
    protected function phpConfigCheck(SymfonyStyle $io): void
    {
        $phpConfig = php_ini_loaded_file();
        $maxSize = UploadedFile::getMaxFilesize();
        $maxPathLength = constant("PHP_MAXPATHLEN");

        $io->note(
            "Loaded PHP Configuration: " . $phpConfig . PHP_EOL . "(might differ from webserver)\n" .
            "Maximum uploadable filesize: " . byte2str($maxSize, BINARY_PREFIX) . "\n" .
            "Maximum path length: " . $maxPathLength . " characters"
        );
    }

    protected function technicalSupportCheck(SymfonyStyle $io): void
    {
        //
        // Technical contact and language
        $technicalRecipient = $this->notifier->getTechnicalRecipient();
        if (is_stringeable($technicalRecipient)) {
            $io->note("Technical recipient configured: " . $technicalRecipient);
        }
    }

    protected function generateSymlinks(SymfonyStyle $io): void
    {
        //
        // Generate flysystem public symlink
        $storageNames = $this->flysystem->getStorageNames(false);
        if ($storageNames) {
            $io->note("Flysystem symlink(s) got generated in public directory.");
        }

        foreach ($storageNames as $storageName) {
            if (!$this->flysystem->hasStorage($storageName . ".public")) {
                continue;
            }

            $realPath = str_rstrip($this->flysystem->prefixPath("", $storageName), "/");

            $publicPath = $this->flysystem->getPublicRoot($storageName . ".public");
            $publicPath = str_rstrip($publicPath, "/");
            if ($realPath == $publicPath) {
                continue;
            }

            if (is_link($publicPath) || file_exists($publicPath)) {
                if (is_link($publicPath)) {
                    unlink($publicPath);
                } elseif (is_emptydir($publicPath)) {
                    rmdir($publicPath);
                } else {
                    exit("Public path \"$publicPath\" already exists but it is not a symlink\n");
                }
            }

            symlink($realPath, $publicPath);
        }
    }
}
