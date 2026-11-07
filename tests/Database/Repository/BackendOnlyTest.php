<?php

namespace Tests\Base\Database\Repository;

use Base\Database\Repository\BackendOnly;
use Base\Database\Repository\UnsupportedQueryException;
use Base\Repository\Aliases;
use Base\Repository\Analytics\PageViews;
use Base\Repository\Layout\Redirections;
use PHPUnit\Framework\TestCase;

/**
 * The repository methods no finder compiles (plan "omnibase agnostic", step E2): declared on the
 * core's interfaces, listed here as the inventory recorded them (~/Sites/.agent-briefs/e2), so that
 * a method added to the list or taken off it is a decision, not a drift. Since step F7f each is
 * written by both persistences, omnibridge/doctrine and omnibridge/eloquent; a persistence not
 * named refuses them aloud.
 */
final class BackendOnlyTest extends TestCase
{
    public function testTheMethodsEachPersistenceWritesItself(): void
    {
        $declared = BackendOnly::declared(array_keys(Aliases::INTERFACES));

        self::assertSame([
            'Base\Repository\Analytics\PageViews::dailyBreakdown',
            'Base\Repository\Analytics\PageViews::dailyBreakdownBySource',
            'Base\Repository\Analytics\PageViews::hourlyBreakdown',
            'Base\Repository\Analytics\PageViews::hourlyBreakdownBySource',
            'Base\Repository\Analytics\PageViews::incrementView',
            'Base\Repository\Analytics\PageVisits::countUnique',
            'Base\Repository\Analytics\PageVisits::dailyBreakdown',
            'Base\Repository\Analytics\PageVisits::hourlyBreakdown',
            'Base\Repository\Analytics\PageVisits::recordPresence',
            'Base\Repository\Analytics\Visits::dailyBreakdown',
            'Base\Repository\Analytics\Visits::hourlyBreakdown',
            'Base\Repository\Analytics\Visits::recordPresence',
            'Base\Repository\Analytics\Visits::returning',
            'Base\Repository\Layout\Redirections::hit',
        ], array_keys($declared));

        foreach ($declared as $method => $declaration) {
            self::assertSame(['doctrine', 'eloquent'], $declaration->backends, $method);
            self::assertNotSame('', $declaration->reason, $method);
        }
    }

    public function testAPersistenceNotNamedRefusesThemAloud(): void
    {
        self::assertTrue(BackendOnly::runs(PageViews::class, 'dailyBreakdown', 'doctrine'));
        self::assertTrue(BackendOnly::runs(PageViews::class, 'dailyBreakdown', 'eloquent'));
        self::assertFalse(BackendOnly::runs(PageViews::class, 'dailyBreakdown', 'cycle'));
        self::assertTrue(BackendOnly::runs(PageViews::class, 'countViews', 'cycle'), 'a finder: every persistence compiles it');

        $this->expectException(UnsupportedQueryException::class);
        $this->expectExceptionMessage('Base\Repository\Layout\Redirections::hit() is written for doctrine, eloquent only (a bulk UPDATE');
        BackendOnly::refuse(Redirections::class, 'hit', 'cycle');
    }
}
