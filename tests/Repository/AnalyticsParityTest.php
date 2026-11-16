<?php

namespace Tests\Base\Repository;

use Base\Entity\Analytics\PageView;
use Base\Entity\Analytics\PageVisit;
use Base\Entity\Analytics\Visit;
use Base\Entity\Layout\Redirection;
use Base\Repository\Analytics\PageViewRepository;
use Base\Repository\Analytics\PageVisitRepository;
use Base\Repository\Analytics\VisitRepository;
use Base\Repository\Layout\RedirectionRepository;
use Base\Testing\Engines;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The core's own queries once written per persistence (the analytics, a redirection's hit), written ONCE in the language: the
 * same answers on Doctrine and on Eloquent over the same rows (Base\Testing\Engines; needs both bridges).
 */
final class AnalyticsParityTest extends TestCase
{
    private ?Engines $engines = null;

    protected function setUp(): void
    {
        if (!class_exists(\Omnibridge\Doctrine\EntityManagers::class) || !class_exists(\Omnibridge\Eloquent\EntityStore::class)) {
            self::markTestSkipped('Needs omnibridge/doctrine and omnibridge/eloquent.');
        }
        $this->engines = Engines::of([PageView::class, PageVisit::class, Visit::class, Redirection::class]);
    }

    /** @return array<string, array{0: string}> */
    public static function backends(): array
    {
        return Engines::backends();
    }

    /** @dataProvider backends (PHPUnit 9, the harness's) */

    #[DataProvider('backends')]
    public function testPageViews(string $backend): void
    {
        $views = new PageViewRepository(($this->engines ?? throw new \LogicException())->finders($backend));
        $paris = new \DateTimeZone('Europe/Paris');
        $views->incrementView('/a', new \DateTimeImmutable('2026-11-15 10:20:00', $paris));   // 09:20 UTC
        $views->incrementView('/a', new \DateTimeImmutable('2026-11-15 10:50:00', $paris));   // the same UTC hour
        $views->incrementView('/a', new \DateTimeImmutable('2026-11-15 12:05:00', $paris), PageView::SOURCE_BOT);
        $views->incrementView('/b', new \DateTimeImmutable('2026-11-16 08:00:00', $paris));
        $since = new \DateTimeImmutable('2026-11-01 00:00:00', new \DateTimeZone('UTC'));

        self::assertSame(4, $views->countViews());
        self::assertSame(['2026-11-15' => 3, '2026-11-16' => 1], $views->dailyBreakdown($since));
        self::assertSame(['2026-11-15' => 3], $views->dailyBreakdown($since, ['/a']));
        self::assertSame([], $views->dailyBreakdown($since, []));
        self::assertSame(['2026-11-15 09:00:00' => 2, '2026-11-15 11:00:00' => 1, '2026-11-16 07:00:00' => 1], $views->hourlyBreakdown($since));
        self::assertSame(['2026-11-15' => ['human' => 2, 'bot' => 1, 'ai' => 0], '2026-11-16' => ['human' => 1, 'bot' => 0, 'ai' => 0]], $views->dailyBreakdownBySource($since));
        self::assertSame(['2026-11-15 11:00:00' => ['human' => 0, 'bot' => 1, 'ai' => 0]], $views->hourlyBreakdownBySource(new \DateTimeImmutable('2026-11-15 10:00:00', new \DateTimeZone('UTC')), '/a'));
    }

    /** @dataProvider backends (PHPUnit 9, the harness's) */

    #[DataProvider('backends')]
    public function testVisitsAndPageVisits(string $backend): void
    {
        $finders = ($this->engines ?? throw new \LogicException())->finders($backend);
        $visits = new VisitRepository($finders);
        $pages = new PageVisitRepository($finders);
        $utc = new \DateTimeZone('UTC');
        $at = new \DateTimeImmutable('2026-11-15 10:20:00', $utc);
        foreach ([['v1', $at], ['v1', $at->modify('+10 minutes')], ['v2', $at], ['v1', $at->modify('+1 day')], ['v3', $at->modify('+1 day')]] as [$subject, $when]) {
            $visits->recordPresence('visitor', $subject, $when);
            $pages->recordPresence('/a', 'visitor', $subject, $when);
        }
        $pages->recordPresence('/b', 'visitor', 'v1', $at);
        $since = new \DateTimeImmutable('2026-11-01', $utc);

        self::assertSame(4, $visits->count([]), 'once per subject and hour');
        self::assertSame(3, $visits->countUnique('visitor'));
        self::assertSame(['2026-11-15' => 2, '2026-11-16' => 2], $visits->dailyBreakdown('visitor', $since));
        self::assertSame(['2026-11-15 10:00:00' => 2, '2026-11-16 10:00:00' => 2], $visits->hourlyBreakdown('visitor', $since));
        self::assertSame(['previous' => 2, 'returning' => 1], $visits->returning('visitor', new \DateTimeImmutable('2026-11-15', $utc), new \DateTimeImmutable('2026-11-16', $utc)));

        self::assertSame(4, $pages->countUnique(), '(page, subject) pairs');
        self::assertSame(3, $pages->countUnique($since, '/a'));
        self::assertSame(['2026-11-15' => 3, '2026-11-16' => 2], $pages->dailyBreakdown($since));
        self::assertSame(['2026-11-15 10:00:00' => 2, '2026-11-16 10:00:00' => 2], $pages->hourlyBreakdown($since, '/a'));
    }

    /** @dataProvider backends (PHPUnit 9, the harness's) */

    #[DataProvider('backends')]
    public function testARedirectionHit(string $backend): void
    {
        $engines = $this->engines ?? throw new \LogicException();
        $table = $engines->description()->entity(Redirection::class)?->table ?? 'redirection';
        $columns = array_map(static fn ($field) => $field, $engines->description()->entity(Redirection::class)?->fields ?? []);
        $row = [];
        foreach ($columns as $property => $field) {
            if ($field->id) {
                continue;
            }
            $row[$field->column] = match ($property) {
                'hits' => 2,
                default => $field->nullable ? null : (str_contains($field->type, 'int') ? 0 : (str_contains($field->type, 'bool') ? 0 : (str_contains($field->type, 'date') ? '2026-01-01 00:00:00' : 'x'))),
            };
        }
        $id = $engines->insert($table, $row);
        $redirections = new RedirectionRepository($engines->finders($backend));
        $redirection = $redirections->find($id);
        self::assertInstanceOf(Redirection::class, $redirection);
        $redirections->hit($redirection);
        $redirections->hit($redirection);

        $read = $redirections->findValuesBy(['hits', 'lastHitAt'], ['id' => $id])[0];
        self::assertSame(4, (int) $read['hits']);
        self::assertNotNull($read['lastHitAt']);
    }
}
