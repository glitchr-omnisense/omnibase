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

    // ── Regressions for bugs found in a 2026-07-08 audit of this file ──
    // Each of these was a confirmed defect, verified against actual behavior
    // before the fix.

    /** while(array_pop($a)){} stopped at the first falsy element. */
    public function testArrayClearEmptiesEvenWithFalsyElements(): void
    {
        $a = [3, 0, 5, '', null];
        array_clear($a);
        $this->assertSame([], $a);
    }

    /** ARRAY_FILTER_USE_BOTH callback is ($value,$key); params were bound backwards. */
    public function testArrayContainsMatchesKeysByDefaultAndValuesOnDemand(): void
    {
        $this->assertSame(['foo' => 'bar'], array_contains(['foo' => 'bar'], 'foo'));
        $this->assertSame([], array_contains(['foo' => 'bar'], 'bar'));
        $this->assertSame(['foo' => 'bar'], array_contains(['foo' => 'bar'], 'bar', ARRAY_USE_VALUES));
    }

    /** getimagesize() returns false on a non-image → array_key_exists(...,false) TypeError. */
    public function testIsRgbReturnsFalseForANonImageInsteadOfThrowing(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'notimg');
        file_put_contents($tmp, 'not an image');
        try {
            $this->assertFalse(is_rgb($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    /** Fragment was read from an undefined $query instead of $parse → always stripped. */
    public function testFormatUrlPreservesTheFragment(): void
    {
        $this->assertStringContainsString('#section', format_url('http://ex.com/p#section'));
    }

    /** Walked consecutive indices ($i/$i+1) instead of stepping by 2. */
    public function testMakePairStepsByTwo(): void
    {
        $this->assertSame(['a' => 'b', 'c' => 'd'], make_pair(['a', 'b', 'c', 'd']));
        $this->assertFalse(make_pair(['a', 'b', 'c']));
    }

    /** SHORTEN_FRONT started the substring at end-of-string ($nChr) → "". */
    public function testStrShortenFrontKeepsTheTail(): void
    {
        $out = str_shorten('Lorem ipsum dolor sit amet', 10, SHORTEN_FRONT);
        $this->assertStringContainsString('amet', $out);
    }

    /** array_unique on a list-of-arrays (SORT_STRING) collapsed everything to one. */
    public function testGetPermutationsWithoutDuplicatesKeepsDistinctTuples(): void
    {
        $perms = get_permutations([1, 2], false);
        $this->assertCount(4, $perms);
        $this->assertContainsEquals([1, 2], $perms);
        $this->assertContainsEquals([2, 1], $perms);
    }

    /** $array was by value → the splice was discarded. */
    public function testArrayOccurrenceRemovesMutatesTheCaller(): void
    {
        $a = [1, 2, 2, 3];
        array_occurrence_removes($a, 2);
        $this->assertSame([1, 2, 3], array_values($a));
    }

    /** str_replace's 4th arg is an output count, not a limit → replaced all. */
    public function testStrReplacePrefixAndSuffixReplaceOnlyTheEnds(): void
    {
        $this->assertSame('Xan', str_replace_prefix('an', 'X', 'anan'));
        $this->assertSame('anX', str_replace_suffix('an', 'X', 'anan'));
        $this->assertSame('anan', str_replace_prefix('zz', 'X', 'anan'));
    }

    /** by-value → the usort sorted a discarded copy. */
    public function testUsortEndsWithReordersTheCaller(): void
    {
        $a = ['xa', 'xb', 'ya'];
        usort_endsWith($a, 'a');
        // Elements NOT ending in 'a' sort before those that do.
        $this->assertSame('xb', $a[0]);
    }

    /** cast_datetime(null) is null; ->setTime()/->setDate() on the null defaults fataled. */
    public function testDateAndTimeIsBetweenDoNotFatalOnDefaultBounds(): void
    {
        $this->assertFalse(date_is_between('2026-01-01'));
        $this->assertFalse(time_is_between('12:00:00'));
        $this->assertIsBool(time_is_between('07:00:00', '05:00:00', '10:00:00'));
    }

    /** ($alpha * 0xFF) concatenated a raw decimal instead of a hex byte. */
    public function testAlpha2hexProducesATwoDigitHexByte(): void
    {
        $this->assertSame('#FF', alpha2hex(1.0));
        $this->assertSame('#80', alpha2hex(0.5));
        $this->assertSame('#00', alpha2hex(0.0));
        $this->assertSame('FF', alpha2hex(1.0, false));
    }
}
