<?php

namespace Base\Console\Task;

use Base\Console\Commands;
use Base\Console\Report;
use Base\Demo\DemoAccountFactory;
use Base\Demo\DemoAccountRegistry;
use Base\Demo\DemoGuard;
use Base\Demo\DemoMode;
use Base\Exception\DemoRefusedException;
use Omnibridge\Persistence\UnitOfWork;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Lock\LockFactory;

/**
 * demo:reset (plan "omnibase agnostic", step F5e, slice 3): the demonstration back to what the fixtures say - what
 * the `cron` container runs every night in the `demo` environment, and nowhere else.
 *
 *  1. refused outside `demo`, and on what DemoGuard refuses (debug, the production database);
 *  2. under a lock (symfony/lock): two resets never overlap;
 *  3. the folders of base.demo.reset.purge emptied - the files the visitors sent;
 *  4. the fixtures reloaded (the host's doctrine:fixtures:load, the groups named), then the declared demonstration
 *     accounts they left out created (by DemoAccountFactory, where the host has one);
 *  5. the uploaded files no row names any more deleted (uploader:entities --delete-orphans, unless
 *     base.demo.reset.orphans is off);
 *  6. the caches that would go on answering yesterday's rows emptied: every cache pool (cache:pool:clear --all),
 *     then the host's second-level cache.
 *
 * Each step is a line of the Report, timestamped, and logged; the Report fails when one failed.
 */
final class DemoReset
{
    public const LOCK = 'base-demo-reset';

    /** The host's commands the reset runs (Commands answers them by these names). */
    public const FIXTURES = 'doctrine:fixtures:load';
    public const ORPHANS = 'uploader:entities';
    public const POOLS = 'cache:pool:clear';

    /**
     * @param UnitOfWork|\Closure(): ?UnitOfWork|null $unitOfWork the persistence the created accounts are flushed to (a closure:
     *                                                     asked only then, as the host's manager may not be made before)
     * @param ?\Closure(): void                        $evict      the host's second-level cache emptied
     */
    public function __construct(
        private readonly DemoMode $mode,
        private readonly DemoGuard $guard,
        private readonly ?DemoAccountFactory $accounts = null,
        private readonly UnitOfWork|\Closure|null $unitOfWork = null,
        private readonly ?LockFactory $lockFactory = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?DemoAccountRegistry $registry = null,
        private readonly ?\Closure $evict = null,
    ) {
    }

    public function run(DemoResetInput $input, Commands $commands, ?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        if (!$this->mode->isActive()) {
            return $report->line(sprintf('<error>demo:reset runs in the "%s" environment only: it empties the database it is given.</error>', DemoMode::ENVIRONMENT))->fail();
        }

        try {
            $this->guard->check();
        } catch (DemoRefusedException $e) {
            return $report->line('<error>'.$e->getMessage().'</error>')->fail();
        }

        if (null === $this->lockFactory) {
            return $report->line('<error>demo:reset needs symfony/lock (composer require symfony/lock): two resets must never run at once.</error>')->fail();
        }

        $lock = $this->lockFactory->createLock(self::LOCK, 3600);
        if (!$lock->acquire()) {
            $this->say($report, 'another reset is running: nothing done.', 'warning');

            return $report->fail();
        }

        $started = microtime(true);
        try {
            $this->say($report, 'reset started.');

            if (!$input->keepFiles) {
                foreach ($input->purge as $directory) {
                    $this->say($report, sprintf('%d file(s) removed from %s.', self::emptyDirectory($directory), $directory));
                }
            }

            if (Report::SUCCESS !== $status = $this->call($commands, $report, self::FIXTURES, $input->groups ? ['--group' => $input->groups] : [])) {
                $this->say($report, 'the fixtures did not load (is DoctrineFixturesBundle registered for demo in config/bundles.php?).', 'error');

                return $report->fail($status);
            }
            $this->say($report, 'fixtures reloaded.');

            if (null === $this->accounts) {
                $this->say($report, 'no demonstration accounts\' factory on this host: the accounts the fixtures left out are not created.', 'warning');
            } elseif ($missing = $this->accounts->missing()) {
                $this->accounts->load();
                ($this->unitOfWork instanceof \Closure ? ($this->unitOfWork)() : $this->unitOfWork)?->flush();
                $this->say($report, sprintf('demonstration account(s) created: %s.', implode(', ', $missing)));
            }

            if ($roles = $this->registry?->missingRoles()) {
                $this->say($report, sprintf('no demonstration account for %s: declare one for each role below the back office (DemoAccountProviderInterface).', implode(', ', $roles)), 'warning');
            }

            if ($input->orphans && !$input->keepFiles) {
                $ok = Report::SUCCESS === $this->call($commands, $report, self::ORPHANS, ['--delete-orphans' => true]);
                $this->say($report, $ok ? 'orphan uploads deleted.' : 'the orphan uploads could not be deleted.', $ok ? 'info' : 'warning');
            }

            $ok = Report::SUCCESS === $this->call($commands, $report, self::POOLS, ['--all' => true]);
            if (null !== $this->evict) {
                ($this->evict)();
            }
            $this->say($report, $ok ? 'cache pools and second-level cache emptied.' : 'the cache pools could not be emptied.', $ok ? 'info' : 'warning');

            $this->say($report, sprintf('reset done in %.1f s.', microtime(true) - $started));

            return $report;
        } catch (\Throwable $e) {
            $this->say($report, sprintf('reset failed: %s', $e->getMessage()), 'error');

            return $report->fail();
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, mixed> $arguments */
    private function call(Commands $commands, Report $report, string $command, array $arguments): int
    {
        $status = $commands->run($command, $arguments);
        if (null === $status) {
            $this->say($report, sprintf('command "%s" not found.', $command), 'warning');

            return Report::FAILURE;
        }

        return $status;
    }

    /** Everything inside a folder, the folder kept (it may be a mounted volume); the number of files removed. */
    private static function emptyDirectory(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $files = iterator_count((new Finder())->in($directory)->files()->ignoreDotFiles(false)->ignoreVCS(false));
        (new Filesystem())->remove(iterator_to_array((new Finder())->in($directory)->depth(0)->ignoreDotFiles(false)->ignoreVCS(false), false));

        return $files;
    }

    private function say(Report $report, string $message, string $level = 'info'): void
    {
        $report->line(sprintf('%s [demo:reset] %s', gmdate('Y-m-d\TH:i:s\Z'), $message));
        $this->logger?->log($level, 'demo:reset: '.$message);
    }
}
