<?php

namespace Tests\Base\Repository;

use Base\Contract\Environment;
use Base\Contract\Locales;
use Base\Contract\Runtime;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Thread;
use Base\Testing\Engines;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Base\Fixtures\Lineage\LineageArticle;
use Tests\Base\Fixtures\Lineage\LineageDestination;

/**
 * A parent read from the database is a managed entity, not a new one: an entity of a JOINED hierarchy (a site's
 * article under Base\Entity\Thread) read with its parent (a destination of the same hierarchy, read at once since its
 * class has subclasses), changed and saved; then a new child given that parent, saved. Doctrine and Eloquent alike
 * (lesringostars' twin: "A new entity was found through the relationship Article::$parent", on Eloquent only). And such
 * entities ordered by their parent, an association.
 */
final class ParentReadThenSavedParityTest extends TestCase
{
    private static ?Engines $engines = null;

    /** @var array{destination: int, article: int} */
    private static array $ids;

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(\Omnibridge\Doctrine\EntityManagers::class) || !class_exists(\Omnibridge\Eloquent\EntityStore::class)) {
            return;
        }
        // A thread's constructor and its translations read the runtime's locale and environment: one, as a host gives them.
        if (!Runtime::has(Locales::class)) {
            Runtime::set(Locales::class, new class implements Locales {
                public function getLocale(): string { return 'en'; }
                public function getDefaultLocale(): string { return 'en'; }
                public function getAvailableLocales(): array { return ['en']; }
                public function getTimezone(): string { return 'UTC'; }
            });
        }
        if (!Runtime::has(Environment::class)) {
            Runtime::set(Environment::class, new class implements Environment {
                public function name(): string { return 'prod'; }
                public function isDebug(): bool { return false; }
                public function secret(): string { return 'test-secret'; }
            });
        }
        self::$engines = Engines::of([Thread::class, LineageDestination::class, LineageArticle::class]);
        $destination = self::thread(LineageDestination::class);
        self::$ids = ['destination' => $destination, 'article' => self::thread(LineageArticle::class, $destination)];
    }

    protected function setUp(): void
    {
        if (null === self::$engines) {
            self::markTestSkipped('Needs omnibridge/doctrine and omnibridge/eloquent.');
        }
    }

    /** @return array<string, array{0: string}> */
    public static function backends(): array
    {
        return Engines::backends();
    }

    /** @dataProvider backends (PHPUnit 9, the harness's) */
    #[DataProvider('backends')]
    public function testAnEntityReadWithItsParentIsSaved(string $backend): void
    {
        $finders = self::finders($backend);
        $article = (new ServiceEntityRepository($finders, LineageArticle::class))->find(self::$ids['article']);
        self::assertInstanceOf(LineageArticle::class, $article);
        $parent = $article->getParent();
        self::assertInstanceOf(LineageDestination::class, $parent, 'the parent read in its own class');
        self::assertTrue($finders->unitOfWork()->contains($parent), 'the parent read is managed');

        $article->setSlug('saved-'.$backend);
        $finders->unitOfWork()->persist($article);
        $finders->unitOfWork()->flush();

        self::assertSame(self::$ids['destination'], (int) self::$engines->pdo()->query('SELECT '.self::parentColumn().' FROM "'.self::table(Thread::class).'" WHERE id = '.self::$ids['article'])->fetchColumn(), 'its parent kept');
    }

    /** @dataProvider backends (PHPUnit 9, the harness's) */
    #[DataProvider('backends')]
    public function testAParentReadIsGivenANewChild(string $backend): void
    {
        $finders = self::finders($backend);
        $destination = (new ServiceEntityRepository($finders, Thread::class))->find(self::$ids['destination']);
        self::assertInstanceOf(LineageDestination::class, $destination, 'read through the root, in its own class');

        $child = new LineageArticle(null, $destination, 'A child of '.$backend);
        // Its uuid, slug and moments as #[GenerateUuid], #[Slugify] and #[Timestamp] give them: Engines' Eloquent store
        // runs no lifecycle listener (a host's does).
        (function (): void {
            $this->uuid ??= bin2hex(random_bytes(16));
            $this->createdAt ??= new \DateTime('2026-01-01 00:00:00');
            $this->updatedAt ??= new \DateTime('2026-01-01 00:00:00');
        })->call($child);
        $child->setSlug('child-of-'.$backend);
        $finders->unitOfWork()->persist($child);
        $finders->unitOfWork()->flush();

        self::assertNotNull($child->getId());
        self::assertSame(self::$ids['destination'], (int) self::$engines->pdo()->query('SELECT '.self::parentColumn().' FROM "'.self::table(Thread::class).'" WHERE id = '.(int) $child->getId())->fetchColumn());
    }

    /**
     * Ordered by an association (an admin's sort on its column, "parent" => ASC): by its target's identifier, as by a
     * field of it ("parent.slug"). Doctrine wrote "ORDER BY e_parent", no DQL ("e_album does not point to a
     * ResultVariable", panoboutique's /admin/photos).
     *
     * @dataProvider backends (PHPUnit 9, the harness's)
     */
    #[DataProvider('backends')]
    public function testOrderedByAnAssociation(string $backend): void
    {
        $first = self::thread(LineageDestination::class);
        $second = self::thread(LineageDestination::class);
        $underSecond = self::thread(LineageArticle::class, $second);
        $underFirst = self::thread(LineageArticle::class, $first);
        $articles = new ServiceEntityRepository(self::finders($backend), LineageArticle::class);
        $ids = static fn (array $threads): array => array_map(static fn (Thread $thread): int => (int) $thread->getId(), $threads);

        self::assertSame([$underFirst, $underSecond], $ids($articles->findBy(['id' => [$underSecond, $underFirst]], ['parent' => 'ASC'])));
        self::assertSame([$underSecond, $underFirst], $ids($articles->findBy(['id' => [$underSecond, $underFirst]], ['parent' => 'DESC'])));
        self::assertSame([$underFirst, $underSecond], $ids($articles->findBy(['id' => [$underSecond, $underFirst]], ['parent.id' => 'ASC'])), 'by a field of it');
    }

    /**
     * A criterion naming an entity persisted but not flushed (no identifier): no row is its, so nothing matches - "no
     * row", not an exception (taratata's importer; Doctrine's finders answered so before 6.0). In a list, the others.
     *
     * @dataProvider backends (PHPUnit 9, the harness's)
     */
    #[DataProvider('backends')]
    public function testAnUnflushedEntityMatchesNoRow(string $backend): void
    {
        $finders = self::finders($backend);
        $unflushed = new LineageDestination(null, null, 'Not written yet');
        $finders->unitOfWork()->persist($unflushed);
        $destination = (new ServiceEntityRepository($finders, LineageDestination::class))->find(self::$ids['destination']);
        $articles = new ServiceEntityRepository($finders, LineageArticle::class);

        self::assertNull($articles->findOneBy(['parent' => $unflushed]), 'no row');
        self::assertSame(0, $articles->count(['parent' => $unflushed]));
        $kept = $articles->findBy(['parent' => [$unflushed, $destination]]);
        self::assertContains(self::$ids['article'], array_map(static fn (Thread $thread): int => (int) $thread->getId(), $kept), 'the other member of the list kept');
        $finders->unitOfWork()->clear(); // the unflushed entity never written by a later flush
    }

    /** The finders of a backend, its unit of work the runtime's (what the core's entities ask while they are flushed). */
    private static function finders(string $backend): \Base\Database\Repository\Finders
    {
        $finders = self::$engines->finders($backend);
        Runtime::set(\Omnibridge\Persistence\UnitOfWork::class, $finders->unitOfWork());

        return $finders;
    }

    private static function parentColumn(): string
    {
        return self::$engines->description()->entity(Thread::class)?->relations['parent']->joinColumns[0]->name ?? 'parent_id';
    }

    private static function table(string $class): string
    {
        return (string) self::$engines->description()->entity($class)?->table;
    }

    /** A thread of that class by hand: its root row (the discriminator, a parent), then its own table's. */
    private static function thread(string $class, ?int $parent = null): int
    {
        $description = self::$engines->description();
        $root = $description->entity(Thread::class) ?? throw new \LogicException('No Thread.');
        $row = [];
        foreach ($root->fields as $field) {
            if ($field->id || $field->nullable) {
                continue;
            }
            $row[$field->column] = match (true) {
                str_contains($field->type, 'int') || str_contains($field->type, 'bool') || str_contains($field->type, 'decimal') || str_contains($field->type, 'float') => 0,
                str_contains($field->type, 'date') || str_contains($field->type, 'time') => '2026-01-01 00:00:00',
                str_contains($field->type, 'json') || str_contains($field->type, 'array') => '[]',
                default => 'x'.bin2hex(random_bytes(3)),
            };
        }
        $row[(string) $root->inheritance?->column->name] = self::$engines->discriminator($class);
        if (null !== $parent) {
            $row[$root->relations['parent']->joinColumns[0]->name] = $parent;
        }
        $id = self::$engines->insert((string) $root->table, $row);
        $own = $description->entity($class)?->table;
        if (null !== $own && $own !== $root->table) {
            self::$engines->insert($own, ['id' => $id]);
        }

        return $id;
    }
}
