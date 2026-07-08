<?php

namespace Tests\Base;

use PHPUnit\Framework\TestCase;

/**
 * Covers pure helpers from src/Resources/Functions.php (composer "files" autoload).
 */
class FunctionsTest extends TestCase
{
    public function testSign(): void
    {
        $this->assertSame('+', sign(0));
        $this->assertSame('+', sign(42.5));
        $this->assertSame('-', sign(-1));
    }

    public function testIsHex(): void
    {
        $this->assertTrue(is_hex('deadBEEF'));
        $this->assertTrue(is_hex('0xff00'));
        $this->assertFalse(is_hex('xyz'));
        $this->assertFalse(is_hex(''));
    }

    public function testStrtobool(): void
    {
        $this->assertTrue(strtobool(true));
        $this->assertTrue(strtobool('Enabled'));
        $this->assertTrue(strtobool('yes'));
        $this->assertTrue(strtobool('1'));
        $this->assertFalse(strtobool('off'));
        $this->assertFalse(strtobool('0'));
        $this->assertFalse(strtobool('anything-else'));
    }

    public function testFormatBytes(): void
    {
        $this->assertSame('0 B', format_bytes(0));
        $this->assertSame('1 KB', format_bytes(1024));
        $this->assertSame('1.5 KB', format_bytes(1536));
        $this->assertSame('1 MB', format_bytes(1024 ** 2));
        $this->assertSame('1 TB', format_bytes(1024 ** 4));
    }

    public function testFormatUuid(): void
    {
        $this->assertSame(
            '01234567-89ab-cdef-0123-456789abcdef',
            format_uuid('0123456789abcdef0123456789abcdef')
        );
        // Separators are stripped before matching.
        $this->assertSame(
            '01234567-89ab-cdef-0123-456789abcdef',
            format_uuid('01234567-89ab-cdef-0123-456789abcdef')
        );
        $this->assertFalse(format_uuid('not-a-uuid'));
        // Strict mode refuses surrounding garbage.
        $this->assertFalse(format_uuid('zz0123456789abcdef0123456789abcdefzz', true));
    }

    public function testShrinkhex(): void
    {
        $this->assertSame('#FFF', shrinkhex('#FFFFFF'));
        $this->assertSame('#ABC', shrinkhex('#AABBCC'));
        $this->assertSame('#ABC', shrinkhex('#AABBCCFF'));
        $this->assertSame('#123456', shrinkhex('#123456FF'));
        $this->assertSame('#A1B2C3', shrinkhex('#A1B2C3'));
        $this->assertNull(shrinkhex(null));
    }

    public function testExpandhex(): void
    {
        $this->assertSame('#AABBCC', expandhex('#ABC'));
        $this->assertSame('#AABBCCFF', expandhex('#ABC', true));
        $this->assertSame('#AABBCCDD', expandhex('#ABCD'));
        $this->assertSame('#AABBCC', expandhex('#AABBCC'));
        $this->assertNull(expandhex(null));
    }

    /**
     * Regression: usort_column()'s $array parameter used to be by-value, so
     * its internal usort($array, ...) call sorted a local copy that was
     * discarded the moment the function returned — the caller's array was
     * silently left untouched (usort_column() always returned true, having
     * done nothing observable). Found via typesense-bundle's
     * Response::getFacetCounts(), whose sortByName option relied on this to
     * actually reorder facet counts; also left Aco::sortByColor() in this
     * bundle completely non-functional.
     */
    public function testUsortColumnSortsTheCallersArrayInPlace(): void
    {
        $rows = [['value' => 'b'], ['value' => 'a'], ['value' => 'c']];

        $result = usort_column($rows, 'value', fn($a, $b) => strcmp($a, $b));

        $this->assertTrue($result);
        $this->assertSame(['a', 'b', 'c'], array_column($rows, 'value'));
    }
}
