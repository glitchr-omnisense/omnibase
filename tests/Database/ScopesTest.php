<?php

namespace Tests\Base\Database;

use Base\Bridge\Doctrine\DoctrineBackend;
use Base\Bridge\Doctrine\QueryPages;
use Base\Database\Scope\TrashScope;
use Base\Database\Scope\VaultScope;
use Base\Database\UnitOfWorks;
use Base\Entity\Layout\Setting;
use Base\Entity\Layout\SettingIntl;
use Base\Entity\Thread;
use Base\Service\Model\Pages;
use Base\Service\Model\Pagination;
use Base\Service\TrashManager;
use Omnibridge\Persistence\Backend;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Tests\Base\Database\Backend\HostPersistence;

/**
 * The trash and vault filters as scopes, the paginator on Pages (plan "omnibase
 * agnostic", step F2e). The container's backend is the persistence's the host
 * chose (HostPersistence, step F7f); a Doctrine query is paged on Doctrine's
 * entity manager, wherever it is installed.
 */
final class ScopesTest extends KernelTestCase
{
    use HostPersistence;

    public function testTheScopesSayWhatTheyKeep(): void
    {
        $trash = new TrashScope();
        self::assertSame('trash', $trash->name());
        self::assertTrue($trash->appliesTo(Thread::class));
        self::assertFalse($trash->appliesTo(Setting::class));
        self::assertSame(['deletedAt' => null], $trash->criteria(Thread::class));
        self::assertSame([], $trash->criteria(Setting::class));

        $vault = new VaultScope();
        self::assertSame('vault', $vault->name());
        self::assertTrue($vault->appliesTo(SettingIntl::class));
        self::assertFalse($vault->appliesTo(Thread::class));
        self::assertSame(['vault' => [null, 'test']], $vault->criteria(SettingIntl::class, ['environment' => 'test']));

        $this->expectException(\InvalidArgumentException::class);
        $vault->criteria(SettingIntl::class);
    }

    public function testDoctrinesFiltersCompileTheScopes(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $filters = $entityManager->getFilters();

        $trash = $filters->getFilter('trash_filter');
        self::assertSame('t0.deletedAt IS NULL', $trash->addFilterConstraint($entityManager->getClassMetadata(Thread::class), 't0'));
        self::assertSame('', $trash->addFilterConstraint($entityManager->getClassMetadata(Setting::class), 't0'));

        $vault = $filters->getFilter('vault_filter');
        self::assertSame("t0.vault IS NULL OR t0.vault = 'test'", $vault->addFilterConstraint($entityManager->getClassMetadata(SettingIntl::class), 't0'));
    }

    public function testTheBackendDisablesAndEnablesAScope(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $backend = $container->get(Backend::class);
        $doctrine = 'doctrine' === $backend->name();
        if ($doctrine) {
            self::assertInstanceOf(DoctrineBackend::class, $backend);
        }
        self::assertSame($container->get(UnitOfWork::class), $backend->unitOfWork());
        self::assertTrue($backend->supports(Backend::TRANSACTIONS));

        self::assertTrue($backend->isScopeEnabled(TrashScope::NAME));
        $seen = $container->get(TrashManager::class)->withTrash(static fn () => $backend->isScopeEnabled(TrashScope::NAME));
        self::assertFalse($seen, 'the trash is seen inside withTrash()');
        self::assertTrue($backend->isScopeEnabled(TrashScope::NAME), 'and hidden again after');

        $backend->disableScope(TrashScope::NAME);
        self::assertFalse($backend->isScopeEnabled(TrashScope::NAME));
        if ($doctrine) {
            self::assertFalse($container->get('doctrine')->getManager()->getFilters()->isEnabled('trash_filter'), 'Doctrine\'s filter');
        }
        $backend->enableScope(TrashScope::NAME);
        self::assertTrue($backend->isScopeEnabled(TrashScope::NAME));

        $this->expectException(\InvalidArgumentException::class);
        $backend->enableScope('tenant');
    }

    public function testThePaginatorPagesAnyPages(): void
    {
        $pages = new class implements Pages {
            public function count(): int
            {
                return 5;
            }

            public function slice(int $offset, int $limit): array
            {
                return \array_slice(['a', 'b', 'c', 'd', 'e'], $offset, $limit);
            }
        };
        $pagination = new Pagination($pages, $this->createMock(RouterInterface::class));
        $pagination->setPageSize(2);
        $pagination->setPage(3);

        self::assertTrue($pagination->isQuery());
        self::assertSame(5, $pagination->getTotalCount());
        self::assertEquals(3, $pagination->getTotalPages(), "ceil()ed, a float as before");
        self::assertSame(['e'], $pagination->getResult());
        self::assertNull($pagination->getQuery(), 'no query behind these pages');
    }

    public function testADoctrineQueryIsPagedAsBefore(): void
    {
        self::bootKernel();
        if (!static::getContainer()->has('doctrine')) {
            self::markTestSkipped('Doctrine is not installed: no Doctrine query to page.');
        }
        // Doctrine's entity manager, whichever persistence the host chose: the query is its own.
        $unitOfWork = UnitOfWorks::of(static::getContainer()->get('doctrine')->getManager());
        $prefix = 'f2e.pages.'.bin2hex(random_bytes(3)).'.';
        $settings = [];
        foreach (['a', 'b', 'c'] as $name) {
            $unitOfWork->persist($settings[] = new Setting($prefix.$name));
        }
        $unitOfWork->flush();

        try {
            $query = $unitOfWork->entityManager()->createQuery('SELECT s FROM '.Setting::class.' s WHERE s.path LIKE :prefix ORDER BY s.path ASC')->setParameter('prefix', $prefix.'%');
            $pagination = static::getContainer()->get('Base\Service\Paginator')->paginate($query, 1, 2);

            self::assertInstanceOf(QueryPages::class, $pagination->get());
            self::assertSame(3, $pagination->getTotalCount());
            self::assertSame([$prefix.'a', $prefix.'b'], array_map(static fn (Setting $s) => $s->getPath(), $pagination->getResult()));
            self::assertSame(2, $pagination->getQuery()?->getMaxResults());
        } finally {
            foreach ($settings as $setting) {
                $unitOfWork->remove($setting);
            }
            $unitOfWork->flush();
        }
    }
}
