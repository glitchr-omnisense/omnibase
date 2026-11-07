<?php

namespace Tests\Base\Database\Backend;

use Base\Database\UnitOfWorks;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Persistence\Backend;
use Omnibridge\Persistence\UnitOfWork;

/**
 * A behaviour test run on both persistences (plan "omnibase agnostic", step F7c): each test taking a backend from
 * backends() runs once on Doctrine (omnibridge/doctrine's unit of work over the harness's entity manager) and once on
 * omnibridge/eloquent (its unit of work on the same database, omnibase's behaviours and listeners attached as a host
 * attaches them) - the same calls through omnisense/omnibridge's UnitOfWork, the same assertions.
 *
 * The Eloquent run is the harness switch: OMNIBASE_ELOQUENT names omnibridge/eloquent's checkout (with its own
 * vendor/), mounted by docker/compose.eloquent.yml; without it the Eloquent cases are skipped, saying so. In a host
 * on eloquent (a site's base.persistence, step F7f) the Eloquent cases run on the host's own persistence, the switch
 * unneeded; a Doctrine case whose code under test reaches the host's services asks doctrineCaseOnADoctrineHost().
 *
 *     docker compose -f compose.yml -f compose.checkouts.yml -f compose.eloquent.yml run --rm omnibase test
 */
trait BothBackends
{
    private ?object $eloquentPersistence = null;

    /** @return array<string, array{0: string}> */
    public static function backends(): array
    {
        return ['doctrine' => ['doctrine'], 'eloquent' => ['eloquent']];
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    /** The backend's unit of work (one per test for Eloquent: its identity map is the test's). */
    protected function unitOfWork(string $backend): UnitOfWork
    {
        if ('doctrine' === $backend) {
            return UnitOfWorks::of($this->entityManager());
        }

        return $this->eloquent()->unitOfWork();
    }

    /** The entity read through the backend (its identity map first). */
    protected function find(string $backend, string $class, mixed $id): ?object
    {
        return 'doctrine' === $backend ? $this->entityManager()->find($class, $id) : $this->eloquent()->store()->find($class, $id);
    }

    /** The backend's identity map emptied: the next reads make new objects. */
    protected function clear(string $backend): void
    {
        'doctrine' === $backend ? $this->entityManager()->clear() : $this->eloquent()->store()->clear();
    }

    /** The backend's trash scope off (Doctrine's trash_filter): a trashed entity is read again. */
    protected function withoutTrashScope(string $backend): void
    {
        if ('doctrine' === $backend) {
            $filters = $this->entityManager()->getFilters();
            $filters->isEnabled('trash_filter') && $filters->disable('trash_filter');

            return;
        }
        $this->eloquent()->backend()->isScopeEnabled('trash') && $this->eloquent()->backend()->disableScope('trash');
    }

    /**
     * The Eloquent persistence let go after each test: PHPUnit keeps the test cases to the end of the run, and the
     * entity dispatchers made on its unit of work are live ones (AbstractEventDispatcher::live()), which the host's
     * flush at the next kernel's response would flush with a container shut down since.
     *
     * @after
     */
    public function releaseEloquentPersistence(): void
    {
        $this->eloquentPersistence = null;
        gc_collect_cycles();
    }

    /**
     * Doctrine's case skipped where the host is on another persistence: the code under test resolves entities through
     * the host's services (users, mentions), which would hand Doctrine's lifecycle entities it does not manage.
     */
    protected function doctrineCaseOnADoctrineHost(string $backend): void
    {
        if ('doctrine' === $backend && 'doctrine' !== ($host = static::getContainer()->get(Backend::class)->name())) {
            self::markTestSkipped(sprintf('Doctrine\'s case: this host is on %s (base.persistence), whose services would hand Doctrine\'s lifecycle entities it does not manage; the %1$s case runs.', $host));
        }
    }

    /**
     * omnibridge/eloquent's persistence on the harness's database, with omnibase's lifecycle (OmnibaseHarness); the
     * host's own where the host is on eloquent.
     */
    protected function eloquent(): object
    {
        if (null !== $this->eloquentPersistence) {
            return $this->eloquentPersistence;
        }
        $persistence = 'Omnibridge\Eloquent\Omnibase\EloquentPersistence';
        if ('eloquent' === static::getContainer()->get(Backend::class)->name() && static::getContainer()->has($persistence)) {
            return $this->eloquentPersistence = static::getContainer()->get($persistence);
        }
        $package = getenv('OMNIBASE_ELOQUENT');
        if (!$package || !is_file($package.'/tests/Site/autoload.php')) {
            self::markTestSkipped('omnibridge/eloquent is not mounted: the harness switch is OMNIBASE_ELOQUENT (docker/compose.eloquent.yml).');
        }
        require_once $package.'/tests/Site/autoload.php';

        return $this->eloquentPersistence = \Omnibridge\Eloquent\Tests\Support\OmnibaseHarness::persistence(static::getContainer());
    }
}
