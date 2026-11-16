<?php

namespace Tests\Base\Repository;

use Base\Database\Mapping\DescriptionShapes;
use Base\Database\Repository\Finder\Aggregate;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Thread;
use Base\Entity\Thread\Tag;
use Base\Testing\Engines;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An association whose target is written under its App\ name (Base\Entity\Thread::$tags -> App\Entity\Thread\Tag,
 * the alias of Base\Entity\Thread\Tag): the description reads the class it names, and a path through it compiles on
 * both engines (eb-4: "No class App\Entity\Thread\Tag in the description" on Eloquent).
 */
final class AliasPathsParityTest extends TestCase
{
    private ?Engines $engines = null;

    protected function setUp(): void
    {
        if (!class_exists(\Omnibridge\Doctrine\EntityManagers::class) || !class_exists(\Omnibridge\Eloquent\EntityStore::class)) {
            self::markTestSkipped('Needs omnibridge/doctrine and omnibridge/eloquent.');
        }
        $this->engines = Engines::of([Thread::class, Tag::class]);
    }

    /** @return array<string, array{0: string}> */
    public static function backends(): array
    {
        return Engines::backends();
    }

    public function testTheDescriptionReadsAnAppAliasAsItsBaseClass(): void
    {
        $shapes = new DescriptionShapes(($this->engines ?? throw new \LogicException())->description());
        self::assertTrue($shapes->has('App\Entity\Thread\Tag'));
        self::assertSame(Tag::class, $shapes->shape('App\Entity\Thread\Tag')->className());
    }

    /** @dataProvider backends (PHPUnit 9, the harness's) */

    #[DataProvider('backends')]
    public function testAPathThroughAnAliasedTarget(string $backend): void
    {
        $engines = $this->engines ?? throw new \LogicException();
        $threads = new ServiceEntityRepository($engines->finders($backend), Thread::class);

        self::assertSame([], $threads->findValuesBy(['tag' => 'tags.id', 'threads' => Aggregate::count()], [], ['threads' => 'DESC']));
        self::assertSame(0, $threads->count(['tags.id' => [1, 2]]));
        self::assertSame([], $threads->findBy([], ['tags.priority' => 'ASC']));
    }
}
