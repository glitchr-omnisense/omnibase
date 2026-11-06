<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Notifier\Abstract\BaseNotifierInterface;
use Base\Service\FlysystemInterface;
use Base\Service\ParameterBagInterface;

/**
 * cache:clear's part that is omnibase's (plan "omnibase agnostic", step F5e, slice 3; formerly Base\Traits\
 * CacheClearTrait and the command's lock), around the host's own clear, given as a closure:
 *
 *  - one clear at a time: a lock file in the cache folder (stale after LOCK_TTL seconds); a second process
 *    skips, successfully;
 *  - before (omnibase's checks): the PHP configuration, the disk and the memory, the cache folder, Docker, the
 *    extensions, omnibase's components and their fallbacks; the warmers asked; node_modules, the technical
 *    recipient, public/phpinfo.php (written in debug, removed otherwise), the storages' public links once the
 *    application started;
 *  - the host's clear, the counter of clears (CacheClears), the sessions if asked;
 *  - after: the second clear of a first one (base.autoclear: the host's $again), OPcache emptied.
 *
 * Everything said goes to the Report; its status is the host's clear's.
 */
final class CacheClear
{
    public const LOCK_TTL = 120;

    /** @var resource|null */
    private $lock = null;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly ?FlysystemInterface $flysystem = null,
        private readonly ?BaseNotifierInterface $notifier = null,
    ) {
    }

    /**
     * @param \Closure(): int       $clear    the host's clear (its status)
     * @param ?\Closure(): mixed    $again    the host's second clear, in a process of its own
     * @param ?\Closure(): mixed    $sessions the host's sessions killed (--sessions)
     */
    public function run(CacheClearInput $input, \Closure $clear, ?\Closure $again = null, ?\Closure $sessions = null, ?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        if (!$this->acquire(rtrim($input->cacheDir, '/'), $report)) {
            $report->line('<error>Cache clear aborted: another process is already clearing the cache.</error>');

            return $report;
        }

        try {
            $clears = $input->clears();
            if ($input->checks) {
                $this->phpConfigCheck($report);
                $this->diskAndMemoryCheck($report);
                $report->write('<info> [INFO] Cache directory:</info> '.rtrim($input->cacheDir, '/').PHP_EOL, true);
                $this->checkVirtualization($report);
                $this->checkExtensions($report);
                $this->customFeatureWarnings($report, $input);
            }

            $report->line($input->optionalWarmers ? "\n // <info>All</info> cache warmers requested." : "\n // Optional cache warmers disabled.");

            // What reads the application's services runs before the cache goes: once it is cleared, a service the
            // host's container has not built yet cannot be built any more (lesringostars' CacheLifecycleTest).
            if ($input->checks) {
                $this->webpackCheck($report, $input->projectDir);
                $this->technicalSupportCheck($report);
                if (null !== $input->phpInfo) {
                    $this->generatePhpInfo($report, $input->projectDir, !$input->phpInfo);
                }
                if (!$clears->notStarted()) {
                    $this->generateSymlinks($report);
                }
            }

            $status = $clear();
            $clears->increment();
            if ($input->sessions && null !== $sessions) {
                $sessions();
            }

            if ($input->checks) {
                if ($clears->isFirst() && $this->parameterBag->get('base.autoclear') && null !== $again) {
                    $report->warning('Automatic double `cache:clear` is now running to account for base bundle features.');
                    $again();
                }
                if (extension_loaded('Zend OPcache')) {
                    $report->notice('Zend OPcache Cleared (CLI)');
                    \opcache_reset();
                }
            }

            return $report->fail($status);
        } finally {
            $this->release(rtrim($input->cacheDir, '/').'/.lock');
        }
    }

    /** A non-blocking lock in the cache folder, a stale one evicted; false: another process holds it. */
    private function acquire(string $cacheDir, Report $report): bool
    {
        $file = $cacheDir.'/.lock';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $stale = static function () use ($file): bool {
            clearstatcache(true, $file);

            return time() - (int) @filemtime($file) > self::LOCK_TTL;
        };
        if (is_file($file) && $stale()) {
            @unlink($file);
        }

        $handle = @fopen($file, 'c+');
        if (false === $handle) {
            throw new \RuntimeException("Cannot open lock file: {$file}");
        }

        if (!@flock($handle, LOCK_EX | LOCK_NB)) {
            if (!$stale() || !@unlink($file)) {
                $report->line('<comment>Another cache:clear is already running; skip.</comment>');
                @fclose($handle);

                return false;
            }

            @fclose($handle);
            $handle = @fopen($file, 'c+');
            if (false === $handle || !@flock($handle, LOCK_EX | LOCK_NB)) {
                throw new \RuntimeException('Failed to acquire lock after evicting stale lock.');
            }
        }

        @ftruncate($handle, 0);
        @fwrite($handle, json_encode(['pid' => getmypid(), 'time' => time(), 'host' => gethostname()]));
        @fflush($handle);
        @touch($file);
        $this->lock = $handle;

        if (\function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }
        // Its date kept fresh on shutdown, so that a long clear does not look stale.
        register_shutdown_function(static function () use ($file) {
            if (is_file($file)) {
                @touch($file);
            }
        });

        return true;
    }

    private function release(string $file): void
    {
        if ($this->lock) {
            @flock($this->lock, LOCK_UN);
            @fclose($this->lock);
            $this->lock = null;
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function phpConfigCheck(Report $report): void
    {
        $report->notice(
            'Loaded PHP Configuration: '.php_ini_loaded_file().PHP_EOL."(might differ from webserver)\n".
            'Maximum uploadable filesize: '.byte2str(self::maxFilesize(), BINARY_PREFIX)."\n".
            'Maximum path length: '.\constant('PHP_MAXPATHLEN').' characters'
        );
    }

    private function diskAndMemoryCheck(Report $report): void
    {
        $freeSpace = disk_free_space('.');
        $diskSpace = disk_total_space('.');
        $percentSpace = round(100 * ($diskSpace - $freeSpace) / $diskSpace, 2);
        $diskSpaceStr = byte2str($freeSpace).' / '.byte2str($diskSpace).' available ('.$percentSpace.' % used)';

        $memoryLimit = str2dec(ini_get('memory_limit'));
        $memoryLimitStr = $memoryLimit > 1 ? byte2str($memoryLimit, \array_slice(DECIMAL_PREFIX, 0, 3)) : '';

        $fn = $percentSpace > 95 ? 'error' : ($percentSpace > 75 ? 'warning' : 'info');
        $report->write(" <$fn>[".strtoupper($fn).'] Disk space information:</'.$fn.'> '.$diskSpaceStr.PHP_EOL, true);
        if ($memoryLimit > 1) {
            if ($memoryLimit < str2dec('512M')) {
                $report->write(' <warning>[WARNING]</warning> Memory limit is very low.. Please consider increasing it', true);
                $report->write('PHP Memory limit: '.$memoryLimitStr);
            } else {
                $report->write(' <info>[INFO] PHP Memory limit:</info> '.$memoryLimitStr.PHP_EOL, true);
            }
        }
    }

    private function checkVirtualization(Report $report): void
    {
        $isDocker = file_exists('/.dockerenv') || (file_exists('/proc/1/cgroup') && str_contains((string) file_get_contents('/proc/1/cgroup'), 'docker'));
        $report->write($isDocker ? '<info> [INFO]</info> Docker environment used.'.PHP_EOL : '<warning> [WARNING] No Docker environment detected.</warning>'.PHP_EOL, true);
    }

    private function checkExtensions(Report $report): void
    {
        $extensions = [
            'xdebug' => 'Xdebug', 'blackfire' => 'Blackfire', 'git2' => 'Git', 'amqp' => 'AMQP', 'Zend OPcache' => 'OPcache',
            'apcu' => 'APCu', 'igbinary' => 'Igbinary', 'imagick' => 'Imagick', 'gd' => 'GD',
        ];

        $report->write('<info> [INFO] PHP Extensions:</info> (cli and webserver extensions might differ)', true);

        $count = 0;
        $lineBreakCount = (int) ceil(sqrt(\count($extensions)));
        $maxLength = max(array_map('strlen', $extensions));
        foreach ($extensions as $extension => $name) {
            $isLoaded = extension_loaded($extension) && match ($extension) {
                'apcu' => (bool) ini_get('apc.enabled'),
                'gd' => \function_exists('gd_info') && gd_info(),
                'xdebug' => '' !== ini_get('xdebug.mode'),
                'blackfire' => getenv('BLACKFIRE_SERVER_ID') && getenv('BLACKFIRE_SERVER_TOKEN'),
                'Zend OPcache' => 1 == ini_get('opcache.enable'),
                'amqp' => class_exists('AMQPConnection'),
                'imagick' => class_exists('Imagick'),
                'igbinary' => \function_exists('igbinary_serialize'),
                default => true,
            };

            $status = $isLoaded ? '<info>✓</info>' : '<error>✗</error>';
            $report->write("        [$status] ".str_pad($name, $maxLength)."\t", 0 == ++$count % $lineBreakCount);
        }
    }

    private function customFeatureWarnings(Report $report, CacheClearInput $input): void
    {
        $report->write('', true);

        $flag = static fn (mixed $on): string => $on ? '<info>✓</info>' : '<error>✗</error>';
        $useCustomRouter = $this->parameterBag->get('base.router.use_custom');
        $report->table(['Base components', 'Option paths', ''], [
            ['Twig loader', 'base.twig.use_custom', $flag($this->parameterBag->get('base.twig.use_custom'))],
            ['Attribute reader', 'base.attributes.use_custom', $flag($this->parameterBag->get('base.attributes.use_custom'))],
            ['Setting bag', 'base.parameter_bag.use_setting_bag', $flag($this->parameterBag->get('base.parameter_bag.use_setting_bag'))],
            ['Hot parameter bag', 'base.parameter_bag.use_hot_bag', $flag($this->parameterBag->get('base.parameter_bag.use_hot_bag'))],
            ['Custom router', 'base.router.use_custom', $flag($useCustomRouter)],
            ['Custom database', 'base.database.use_custom', $flag($this->parameterBag->get('base.database.use_custom'))],
            ['Mail notification', 'base.notifier.mail', $flag($this->parameterBag->get('base.notifier.mailer'))],
        ]);

        if (true === $useCustomRouter && true === $this->parameterBag->get('base.router.use_fallbacks')) {
            if ($this->parameterBag->get('base.router.fallback_warning') && false === $input->hostFallback) {
                $report->warning('No host fallback configured in `base.yaml`'.PHP_EOL."(configure 'base.router.fallbacks' to remove this message or disable `base.router.fallback_warning` warning).");
            }
            if ($this->parameterBag->get('base.database.fallback_warning') && false === $input->customMetadataFactory) {
                $report->warning('Custom ClassMetadataFactory is configured. No fallback configured in `base.yaml`'.PHP_EOL."(configure 'doctrine.orm.class_metadata_factory_name' to remove this message or disable `base.database.fallback_warning` warning).");
            }
        }
    }

    private function webpackCheck(Report $report, string $projectDir): void
    {
        if (interface_exists('Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface') && !is_dir($projectDir.'/var/modules') && !is_dir($projectDir.'/node_modules')) {
            $report->error('Node package manager directory `'.$projectDir.'/node_modules'.'` is missing. '.PHP_EOL.'Run `npm install` to setup your dependencies !');
        }
    }

    private function technicalSupportCheck(Report $report): void
    {
        $technicalRecipient = $this->notifier?->getTechnicalRecipient();
        if (is_stringeable($technicalRecipient)) {
            $report->notice('Technical recipient configured: '.$technicalRecipient);
        }
    }

    /** public/phpinfo.php written (it shows phpinfo() in debug only), or removed. */
    private function generatePhpInfo(Report $report, string $projectDir, bool $delete): void
    {
        $targetFile = $projectDir.'/public/phpinfo.php';
        $storageNames = $this->flysystem?->getStorageNames(false) ?? [];
        if (!is_file($targetFile) && $storageNames) {
            $report->notice(sprintf("PHP Info file will be generated in the public directory at '%s'.\nDetected storage backends: %s", $targetFile, implode(', ', $storageNames)));
        }

        $alreadyExists = is_file($targetFile);
        if ($delete) {
            if ($alreadyExists) {
                @unlink($targetFile)
                    ? $report->success(sprintf('phpinfo.php successfully removed from %s', $targetFile))
                    : $report->error(sprintf('Failed to remove phpinfo.php from %s', $targetFile));
            }

            return;
        }

        $content = <<<'PHP'
<?php
use App\Kernel;

$_SERVER["APP_TIMER"] = microtime(true);
require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    if (! (bool) ($context['APP_DEBUG'] ?? false)) {
        header("Location: .");
        exit;
    }

    phpinfo();
    phpinfo(INFO_MODULES);
};
PHP;

        if (false === @file_put_contents($targetFile, $content)) {
            $report->error(sprintf('Could not write phpinfo.php to %s', $targetFile));

            return;
        }
        if (!$alreadyExists && file_exists($targetFile)) {
            $report->success(sprintf('phpinfo.php successfully generated at %s', $targetFile));
        }
    }

    /**
     * Each storage's public link in the public folder, replaced in one step (symlink_atomic(): several processes
     * clear the cache at once when a stack starts, and what another one just did is not an error).
     *
     * @throws \RuntimeException a public path that is a folder not empty, or that cannot be removed
     */
    private function generateSymlinks(Report $report): void
    {
        $storageNames = $this->flysystem?->getStorageNames(false) ?? [];
        if ($storageNames) {
            $report->notice('Flysystem symlink(s) got generated in public directory.');
        }

        foreach ($storageNames as $storageName) {
            if (!$this->flysystem->hasStorage($storageName.'.public')) {
                continue;
            }

            $realPath = str_rstrip($this->flysystem->prefixPath('', $storageName), '/');
            $publicPath = str_rstrip($this->flysystem->getPublicRoot($storageName.'.public'), '/');
            if ($realPath == $publicPath) {
                continue;
            }

            $target = relative_path($realPath, \dirname($publicPath));
            if (!is_link($publicPath) && file_exists($publicPath)) {
                if (is_dir($publicPath)) {
                    if (!is_emptydir($publicPath)) {
                        throw new \RuntimeException("Directory \"$publicPath\" exists and is not empty.");
                    }
                    if (!@rmdir($publicPath) && is_dir($publicPath) && !is_link($publicPath)) {
                        throw new \RuntimeException("Directory \"$publicPath\" exists, but you don't have the permissions.");
                    }
                } elseif (is_file($publicPath)) {
                    @unlink($publicPath);
                } else {
                    throw new \RuntimeException("Cannot safely remove \"$publicPath\" — unknown file type.");
                }
            }

            if (!symlink_atomic($target, $publicPath)) {
                $report->warning(sprintf('The public link "%s" could not be made (to "%s").', $publicPath, $target));
            }
        }
    }

    /** The largest file PHP accepts in an upload: the smaller of post_max_size and upload_max_filesize (as Symfony's UploadedFile). */
    private static function maxFilesize(): int|float
    {
        $parse = static function (string $size): int|float {
            if ('' === $size) {
                return 0;
            }
            $size = strtolower($size);
            $max = ltrim($size, '+');
            $max = match (true) {
                str_starts_with($max, '0x') => \intval($max, 16),
                str_starts_with($max, '0') => \intval($max, 8),
                default => (int) $max,
            };

            return $max * match (substr($size, -1)) {
                't' => 1024 ** 4, 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
            };
        };

        return min($parse((string) ini_get('post_max_size')) ?: PHP_INT_MAX, $parse((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX);
    }
}
