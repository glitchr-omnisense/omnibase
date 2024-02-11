<?php

namespace Base\Traits;

use Base\Console\Command\CacheClearCommand;
use Base\Database\Mapping\ClassMetadataFactory;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;
use Symfony\Component\Console\Helper\Table;

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
        $io->write("", true);

        $useCustomLoader = $this->parameterBag->get("base.twig.use_custom");
        $useCustomReader = $this->parameterBag->get("base.annotations.use_custom");
        $useCustomRouter = $this->parameterBag->get("base.router.use_custom");
        $useMailer = $this->parameterBag->get("base.notifier.mailer");
        $useSettingBag = $this->parameterBag->get("base.parameter_bag.use_setting_bag");
        $useHotParameterBag = $this->parameterBag->get("base.parameter_bag.use_hot_bag");
        $useCustomDbFeatures = $this->parameterBag->get("base.database.use_custom");

        $table = new Table($io);
        $table
            ->setHeaders(['Base components', 'Option paths', ''])
            ->setRows([
                ['Twig loader', 'base.twig.use_custom', $useCustomLoader ? "<info>✓</info>" : "<error>✗</error>"],
                ['Annotation reader', 'base.annotations.use_custom',  $useCustomReader ? "<info>✓</info>" : "<error>✗</error>"],
                ['Setting bag', 'base.parameter_bag.use_setting_bag',  $useSettingBag ? "<info>✓</info>" : "<error>✗</error>"],
                ['Hot parameter bag', 'base.parameter_bag.use_hot_bag',  $useHotParameterBag ? "<info>✓</info>" : "<error>✗</error>"],
                ['Custom router', 'base.router.use_custom',  $useCustomRouter ? "<info>✓</info>" : "<error>✗</error>"],
                ['Custom database', 'base.database.use_custom',  $useCustomDbFeatures ? "<info>✓</info>" : "<error>✗</error>"],
                ['Mail notification', 'base.notifier.mail',  $useMailer ? "<info>✓</info>" : "<error>✗</error>"]
            ])
        ;
        $table->render();

        if ($useCustomRouter === true) {
            if ($this->parameterBag->get("base.router.fallback_warning") && !$this->router->getHostFallback()) {
                $io->warning("No host fallback configured in `base.yaml`" . PHP_EOL . "(configure 'base.router.fallbacks' to remove this message or disable `base.router.fallback_warning` warning).");
            }
        }

        if ($this->parameterBag->get("base.database.fallback_warning") && !$this->entityManager->getMetadataFactory() instanceof ClassMetadataFactory) {
            $io->warning("Custom ClassMetadataFactory is configured. No fallback configured in `base.yaml`" . PHP_EOL . "(configure 'doctrine.orm.class_metadata_factory_name' to remove this message or disable `base.database.fallback_warning` warning).");
        }
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
    protected function doubleCacheClearCheck(SymfonyStyle $io)
    {
        if (CacheClearCommand::isFirstClear()) {
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

        $io->write(" <$fn>[" . strtoupper($fn) . "] Disk space information:</$fn> " . $diskSpaceStr . PHP_EOL, true);
        if ($memoryLimit > 1) {

            if ($memoryLimit < str2dec("512M")) {
                $io->write(" <warning>[WARNING]</warning> Memory limit is very low.. Please consider increasing it", true);
                $io->write('PHP Memory limit: ' . $memoryLimitStr);
            } else {
                $io->write(" <info>[INFO] PHP Memory limit:</info> " . $memoryLimitStr . PHP_EOL, true);
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
