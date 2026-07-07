<?php

namespace Tests\Base\Enum;

use Base\Enum\SpamScore;
use PHPUnit\Framework\TestCase;

class SpamScoreTest extends TestCase
{
    public function testIntegerMappingCoversEveryValueInSeverityOrder(): void
    {
        $map = SpamScore::__toInt();

        $this->assertSame([
            SpamScore::NO_TEXT => -1,
            SpamScore::NOT_SPAM => 0,
            SpamScore::MAYBE_SPAM => 1,
            SpamScore::BLATANT_SPAM => 2,
        ], $map);

        // Every permitted value has an integer — and vice versa.
        $this->assertEqualsCanonicalizing(SpamScore::getPermittedValues(), array_keys($map));
    }

    public function testEveryValueHasAnIcon(): void
    {
        foreach (SpamScore::getPermittedValues() as $value) {
            $this->assertNotNull(SpamScore::getIcon($value), $value);
        }
    }
}
