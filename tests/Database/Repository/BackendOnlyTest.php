<?php

namespace Tests\Base\Database\Repository;

use Base\Database\Repository\BackendOnly;
use Base\Database\Repository\UnsupportedQueryException;
use Base\Repository\Aliases;
use Base\Repository\Analytics\PageViews;
use Base\Repository\Layout\Redirections;
use PHPUnit\Framework\TestCase;

/**
 * The repository methods only one persistence runs (plan "omnibase agnostic", step E2): declared on
 * the core's interfaces, listed here as the inventory recorded them (~/Sites/.agent-briefs/e2), so
 * that a method added to the list or taken off it is a decision, not a drift; another persistence
 * refuses them aloud.
 */
final class BackendOnlyTest extends TestCase
{
    public function testTheMethodsDeclaredForDoctrineOnly(): void
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
            self::assertSame(['doctrine'], $declaration->backends, $method);
            self::assertNotSame('', $declaration->reason, $method);
        }
    }

    public function testAnotherPersistenceRefusesThemAloud(): void
    {
        self::assertTrue(BackendOnly::runs(PageViews::class, 'dailyBreakdown', 'doctrine'));
        self::assertFalse(BackendOnly::runs(PageViews::class, 'dailyBreakdown', 'eloquent'));
        self::assertTrue(BackendOnly::runs(PageViews::class, 'countViews', 'eloquent'), 'a finder: every persistence compiles it');

        $this->expectException(UnsupportedQueryException::class);
        $this->expectExceptionMessage('Base\Repository\Layout\Redirections::hit() is written for doctrine only (a bulk DQL UPDATE');
        BackendOnly::refuse(Redirections::class, 'hit', 'eloquent');
    }
}
