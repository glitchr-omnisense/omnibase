<?php

namespace Tests\Base\Database\Common;

use Base\Database\Common\Collections\OrderedArrayCollection;
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
}
