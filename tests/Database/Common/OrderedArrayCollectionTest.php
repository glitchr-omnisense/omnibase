<?php

namespace Tests\Base\Database\Common;

use Base\Database\Common\Collections\OrderedArrayCollection;
use Doctrine\Common\Collections\AbstractLazyCollection;
use PHPUnit\Framework\TestCase;

/**
 * An #[OrderColumn] association reorders itself on first read from the ids
 * stored alongside it. Rows written while an element was still unsaved
 * hold a null there ("[null]"): reading them used to die in array_flip().
 */
class OrderedArrayCollectionTest extends TestCase
{
    // As in a debug kernel: a PHP warning is an error, not a note.
    protected function setUp(): void
    {
        set_error_handler(fn (int $level, string $message) => throw new \ErrorException($message, 0, $level));
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    private function element(int $id): object
    {
        return new class($id) {
            public function __construct(private int $id) {}
            public function getId(): int { return $this->id; }
        };
    }

    public function testElementsFollowTheStoredPositions(): void
    {
        [$a, $b, $c] = [$this->element(1), $this->element(2), $this->element(3)];
        $collection = new OrderedArrayCollection([$a, $b, $c], [3, 1, 2]);

        self::assertSame([$c, $a, $b], $collection->toArray());
    }

    public function testNullPositionsAreIgnoredAndUnknownElementsComeLast(): void
    {
        [$a, $b, $c] = [$this->element(1), $this->element(2), $this->element(3)];
        $collection = new OrderedArrayCollection([$a, $b, $c], [null, 2, null]);

        $ordered = $collection->toArray();
        self::assertSame($b, $ordered[0], 'the one positioned element first');
        self::assertCount(3, $ordered, 'nothing lost');
    }

    public function testOnlyNullsLeaveTheOrderAlone(): void
    {
        [$a, $b] = [$this->element(1), $this->element(2)];
        $collection = new OrderedArrayCollection([$a, $b], [null]);

        self::assertCount(2, $collection->toArray());
    }

    /**
     * Filled by a lazy collection (an association not loaded yet, as
     * OrderColumn::postLoad() leaves it): the positions wait for that load,
     * however this collection is read while it is under way - Doctrine
     * reads it for the entries added before, then for its snapshot.
     */
    public function testThePositionsWaitForTheLazyCollectionToBeLoaded(): void
    {
        [$a, $b, $c] = [$this->element(1), $this->element(2), $this->element(3)];
        $ordered = new OrderedArrayCollection([], [3, 1, 2]);
        $lazy = new class($ordered, [$a, $b, $c]) extends AbstractLazyCollection {
            public array $seen = [];
            public function __construct(private OrderedArrayCollection $inner, private array $rows)
            {
            }
            protected function doInitialize(): void
            {
                $this->collection = $this->inner;
                $this->inner->clear();
                foreach ($this->rows as $row) {
                    $this->inner->add($row);
                    $this->seen[] = $this->inner->toArray(); // a read while it loads
                }
            }
        };
        $ordered->loadedBy($lazy);

        self::assertFalse($ordered->isLoaded());
        self::assertSame([], $ordered->toArray(), 'nothing there before the load');

        self::assertSame([$c, $a, $b], $lazy->toArray());
        self::assertSame([$a, $b, $c], end($lazy->seen), 'read during the load: as loaded, the positions kept');
        self::assertTrue($ordered->isLoaded());
        self::assertSame([], $ordered->getPositions(), 'spent once, on the entries loaded');
    }

    public function testWithoutALazyCollectionItSortsAtOnce(): void
    {
        [$a, $b] = [$this->element(1), $this->element(2)];
        $collection = new OrderedArrayCollection([$a, $b], [2, 1]);

        self::assertTrue($collection->isLoaded());
        self::assertSame([$b, $a], $collection->getValues());
    }

    public function testItIsSerializedAsBefore(): void
    {
        [$a, $b] = [$this->element(1), $this->element(2)];
        $ordered = new OrderedArrayCollection([], [2, 1]);
        $lazy = new class extends AbstractLazyCollection {
            protected function doInitialize(): void
            {
            }
        };
        $ordered->loadedBy($lazy);

        $copy = unserialize(serialize($ordered));
        self::assertInstanceOf(OrderedArrayCollection::class, $copy);
        self::assertSame([2, 1], $copy->getPositions());
        self::assertTrue($copy->isLoaded(), 'the copy is filled by nothing');
    }
}
