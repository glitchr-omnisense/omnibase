<?php

namespace Tests\Base\Service;

use Base\Service\Icons;
use PHPUnit\Framework\TestCase;

/**
 * Icons::iconify() with no adapter that knows a name, not even the question mark: nothing to draw, in the shape asked.
 */
final class IconsTest extends TestCase
{
    public function testAnUnknownIconIsAnEmptyListWhenWrapped(): void
    {
        self::assertSame([], (new Icons([]))->iconify('fa-solid fa-heart'));
    }

    public function testAnUnknownIconIsAnEmptyStringWhenNotWrapped(): void
    {
        self::assertSame('', (new Icons([]))->iconify('fa-solid fa-heart', [], false));
    }

    public function testAnAssociativeMapOfUnknownIconsGivesStringsATemplateCanPrint(): void
    {
        $states = (new Icons([]))->iconify(['liked' => 'fa-solid fa-heart', 'unliked' => 'fa-regular fa-heart']);

        self::assertSame(['liked' => '', 'unliked' => ''], $states);
    }
}
