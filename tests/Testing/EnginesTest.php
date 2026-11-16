<?php

namespace Tests\Base\Testing;

use Base\Database\Repository\EntityRepositories;
use Base\Entity\Layout\Setting;
use Base\Entity\Thread;
use Base\Testing\Engines;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Base\Testing\Engines: the core's entities on both engines over one SQLite database, rows seeded once. */
final class EnginesTest extends TestCase
{
    private static ?Engines $engines = null;

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(\Omnibridge\Doctrine\EntityManagers::class) || !class_exists(\Omnibridge\Eloquent\EntityStore::class)) {
            return;
        }
        self::$engines = Engines::of([Setting::class]);
        // Rows by hand (a Setting's constructor asks the host's translations: seed() takes entities a test can build).
        $table = self::$engines->description()->entity(Setting::class)?->table ?? 'setting';
        foreach (['engines.a', 'engines.b'] as $path) {
            self::$engines->insert($table, ['path' => $path, 'locked' => false]);
        }
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
    public function testTheSameRowsOnEachEngine(string $backend): void
    {
        $engines = self::$engines ?? throw new \LogicException('No engines.');
        $finders = $engines->finders($backend);
        self::assertSame($backend, $finders->backend());
        $settings = (new EntityRepositories($finders))->of(Setting::class);

        self::assertSame(2, $settings->count());
        self::assertSame(['engines.a', 'engines.b'], array_map(static fn (Setting $s): string => $s->getPath(), $settings->findBy([], ['path' => 'ASC'])));
        self::assertSame('common', $engines->discriminator(Thread::class), 'the description\'s value, Doctrine\'s too');
    }

    public function testARowWrittenByHandIsReadByBoth(): void
    {
        $engines = self::$engines ?? throw new \LogicException('No engines.');
        $table = $engines->description()->entity(Setting::class)?->table ?? 'setting';
        $id = $engines->insert($table, ['path' => 'engines.c', 'locked' => false]);
        try {
            foreach (Engines::BACKENDS as $backend) {
                $found = (new EntityRepositories($engines->finders($backend)))->of(Setting::class)->find($id);
                self::assertInstanceOf(Setting::class, $found, $backend);
                self::assertSame('engines.c', $found->getPath());
            }
        } finally {
            $engines->pdo()->exec(sprintf('DELETE FROM "%s" WHERE id = %d', $table, $id));
        }
    }
}
