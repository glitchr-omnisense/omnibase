<?php

namespace Tests\Base\Translation;

use PHPUnit\Framework\TestCase;

/**
 * A double-quoted message written over several lines keeps each following
 * line indented deeper than its key, as YAML 1.2 asks of a flow scalar in a
 * block mapping (its continuation lines belong to the value, at n+1 spaces
 * at least). Symfony's YAML reads a line at the key's own indentation as the
 * rest of the message; a parser that follows the specification (the yaml
 * package of JavaScript, which lesringostars-react reads the catalogues
 * with) refuses the whole file. Indented one step deeper, both read the same
 * message: the line's leading spaces are folded away either way.
 */
class QuotedScalarIndentationTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function catalogues(): iterable
    {
        foreach (glob(\dirname(__DIR__, 2).'/translations/*.yaml') as $file) {
            yield basename($file) => [$file];
        }
    }

    /** @dataProvider catalogues */
    #[\PHPUnit\Framework\Attributes\DataProvider('catalogues')]
    public function testEachLineOfAMultiLineMessageIsIndentedDeeperThanItsKey(string $file): void
    {
        $misplaced = [];
        $open = null; // [the key's indentation, its line] while a quoted message runs on
        foreach (file($file, \FILE_IGNORE_NEW_LINES) as $index => $line) {
            if (null !== $open) {
                if ('' !== trim($line) && strspn($line, ' ') <= $open[0]) {
                    $misplaced[] = sprintf('line %d (the message of line %d): %s', $index + 1, $open[1], trim($line));
                }
                if (self::closes($line)) {
                    $open = null;
                }
                continue;
            }
            if (preg_match('/^( *)[^#\s][^:]*:\s+"(.*)$/', $line, $match) && !self::closes($match[2])) {
                $open = [\strlen($match[1]), $index + 1];
            }
        }

        $this->assertSame([], $misplaced, basename($file).': a line of a quoted message at its key\'s indentation, or less');
    }

    /** Whether an unescaped double quote ends the message on this line. */
    private static function closes(string $text): bool
    {
        return 1 === preg_match('/(?<!\\\\)(?:\\\\\\\\)*"/', $text);
    }
}
