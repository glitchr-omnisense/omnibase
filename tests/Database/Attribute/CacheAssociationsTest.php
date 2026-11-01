<?php

namespace Tests\Base\Database\Attribute;

use Base\Database\Attribute\Cache;
use PHPUnit\Framework\TestCase;

/**
 * #[Cache(associations: ...)] caches the associations of the kinds it names,
 * numbered as Doctrine's ClassMetadata numbers them. MANY_TO_MANY was the
 * string "ONE_TO_ONE", so associations: "MANY_TO_MANY" cached none and
 * associations: Cache::MANY_TO_MANY cached the one-to-one associations.
 */
class CacheAssociationsTest extends TestCase
{
    public function testEachKindNamesItsOwnAssociations(): void
    {
        $this->assertSame(Cache::FLAG_ONE_TO_ONE, (new Cache(associations: 'ONE_TO_ONE'))->associations);
        $this->assertSame(Cache::FLAG_MANY_TO_ONE, (new Cache(associations: 'MANY_TO_ONE'))->associations);
        $this->assertSame(Cache::FLAG_ONE_TO_MANY, (new Cache(associations: 'ONE_TO_MANY'))->associations);
        $this->assertSame(Cache::FLAG_MANY_TO_MANY, (new Cache(associations: 'MANY_TO_MANY'))->associations);
        $this->assertSame(Cache::FLAG_MANY_TO_MANY, (new Cache(associations: Cache::MANY_TO_MANY))->associations);
    }

    public function testTheGroupsAndNone(): void
    {
        $this->assertSame(Cache::FLAG_ONE_TO_ONE | Cache::FLAG_MANY_TO_ONE, (new Cache(associations: 'TO_ONE'))->associations);
        $this->assertSame(Cache::FLAG_ONE_TO_MANY | Cache::FLAG_MANY_TO_MANY, (new Cache(associations: 'TO_MANY'))->associations);
        $this->assertSame(15, (new Cache(associations: 'ALL'))->associations);
        $this->assertSame(0, (new Cache(associations: 'NONE'))->associations);
        $this->assertSame(0, (new Cache())->associations);
    }

    public function testTheKindsAreDistinct(): void
    {
        $kinds = [Cache::ONE_TO_ONE, Cache::MANY_TO_ONE, Cache::ONE_TO_MANY, Cache::MANY_TO_MANY, Cache::TO_ONE, Cache::TO_MANY, Cache::ALL, Cache::NONE];

        $this->assertSame($kinds, array_values(array_unique($kinds)));
    }
}
