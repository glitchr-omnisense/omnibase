<?php

namespace Tests\Base\Service;

use Base\Service\Analytics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * End-to-end coverage of Base\Service\Analytics against a real database -
 * exercises the repositories' upsert SQL too (not mocked), since the whole
 * point of this service is that concurrent/repeat requests behave
 * correctly at the SQL level (INSERT ... ON DUPLICATE KEY UPDATE / INSERT
 * IGNORE), not just that the PHP call graph is wired correctly.
 *
 * Runs under the host app's PHPUnit (`make tests glitchr`, KERNEL_CLASS=App\Kernel).
 */
class AnalyticsTest extends KernelTestCase
{
    // A random-suffixed path/subject namespace per test RUN (not per test
    // method) keeps this test isolated from real traffic and from any
    // previous run's leftover rows without needing transactional rollback
    // machinery - cleaned up explicitly in tearDown() regardless.
    private static string $runId;

    private EntityManagerInterface $em;
    private Analytics $analytics;

    public static function setUpBeforeClass(): void
    {
        self::$runId = bin2hex(random_bytes(4));
    }

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (run via `make tests glitchr`).');
        }

        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get('doctrine')->getManager();
        $this->analytics = $container->get(Analytics::class);
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement("DELETE FROM analytics_page_view WHERE path LIKE :p", ["p" => "/test-" . self::$runId . "%"]);
        $connection->executeStatement("DELETE FROM analytics_visit WHERE subject_id LIKE :s", ["s" => "test-" . self::$runId . "%"]);
    }

    private function path(string $suffix = ""): string
    {
        return "/test-" . self::$runId . $suffix;
    }

    private function subject(string $suffix = ""): string
    {
        return "test-" . self::$runId . $suffix;
    }

    public function testPageViewsIncrementsOnEachTrack(): void
    {
        $path = $this->path("-views");

        $this->analytics->track($path);
        $this->analytics->track($path);
        $this->analytics->track($path);

        $this->assertSame(3, $this->analytics->pageViews($path));
    }

    public function testPageViewsSumsAcrossPagesWhenPathIsNull(): void
    {
        $a = $this->path("-a");
        $b = $this->path("-b");

        $this->analytics->track($a);
        $this->analytics->track($a);
        $this->analytics->track($b);

        // sums every OTHER page's real traffic too - assert on the delta,
        // not an absolute count
        $before = $this->analytics->pageViews();
        $this->analytics->track($this->path("-c"));
        $after = $this->analytics->pageViews();

        $this->assertSame(1, $after - $before);
        $this->assertSame(2, $this->analytics->pageViews($a));
        $this->assertSame(1, $this->analytics->pageViews($b));
    }

    public function testUniqueVisitorsDeduplicatesRepeatVisitsSameDay(): void
    {
        $visitor = $this->subject("-v1");

        $this->analytics->track($this->path("-x"), $visitor);
        $this->analytics->track($this->path("-y"), $visitor); // same visitor, different page, same day
        $this->analytics->track($this->path("-z"), $visitor);

        // page views still counted 3 times (raw hits)...
        $this->assertSame(1, $this->analytics->pageViews($this->path("-x")));
        $this->assertSame(1, $this->analytics->pageViews($this->path("-y")));
        $this->assertSame(1, $this->analytics->pageViews($this->path("-z")));

        // ...but the visitor is counted once, not three times
        $connection = $this->em->getConnection();
        $count = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM analytics_visit WHERE subject_type = 'visitor' AND subject_id = :id",
            ["id" => $visitor],
        );
        $this->assertSame(1, $count);
    }

    public function testTrackWithoutAVisitorCookieCountsThePageViewButNoVisitor(): void
    {
        $path = $this->path("-anon");

        $this->analytics->track($path, null, null);

        $this->assertSame(1, $this->analytics->pageViews($path));

        $connection = $this->em->getConnection();
        $count = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM analytics_visit WHERE date = CURDATE() AND subject_id LIKE :id",
            ["id" => "test-" . self::$runId . "%"],
        );
        // no visitor/user rows at all from this test's own subjects,
        // since none of the earlier assertions in THIS method registered one
        $this->assertSame(0, $count);
    }

    public function testUniqueUsersIsIndependentOfUniqueVisitors(): void
    {
        $sharedPath = $this->path("-shared");
        $visitor = $this->subject("-anon-visitor");
        $user = $this->subject("-user-1");

        // an anonymous (cookie-consented) visit and an authenticated visit,
        // same page, same day, different subject namespaces entirely
        $this->analytics->track($sharedPath, $visitor, null);
        $this->analytics->track($sharedPath, null, $user);

        $connection = $this->em->getConnection();
        $visitorCount = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM analytics_visit WHERE subject_type = 'visitor' AND subject_id = :id",
            ["id" => $visitor],
        );
        $userCount = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM analytics_visit WHERE subject_type = 'user' AND subject_id = :id",
            ["id" => $user],
        );

        $this->assertSame(1, $visitorCount);
        $this->assertSame(1, $userCount);
        // the page itself was hit twice, regardless of who by
        $this->assertSame(2, $this->analytics->pageViews($sharedPath));
    }

    public function testTodayWindowExcludesOlderDays(): void
    {
        $path = $this->path("-window");
        $connection = $this->em->getConnection();

        // seed a row for yesterday directly (Analytics::track() only ever
        // writes "today" - this simulates a page that was viewed
        // yesterday but not since)
        $connection->executeStatement(
            "INSERT INTO analytics_page_view (path, date, views) VALUES (:path, :date, 5)",
            ["path" => $path, "date" => (new \DateTimeImmutable("-1 day"))->format("Y-m-d")],
        );

        $this->assertSame(5, $this->analytics->pageViews($path), "all-time must include yesterday's row");
        $this->assertSame(0, $this->analytics->pageViews($path, "today"), "today's window must exclude yesterday's row");

        $this->analytics->track($path);
        $this->assertSame(1, $this->analytics->pageViews($path, "today"));
        $this->assertSame(6, $this->analytics->pageViews($path), "all-time now sums both days");
    }

    public function testSummaryReturnsAllFourWindowsForAllThreeCounters(): void
    {
        $summary = $this->analytics->summary();

        foreach (["today", "7d", "30d", "all"] as $window) {
            $this->assertArrayHasKey($window, $summary);
            $this->assertArrayHasKey("pageViews", $summary[$window]);
            $this->assertArrayHasKey("uniqueVisitors", $summary[$window]);
            $this->assertArrayHasKey("uniqueUsers", $summary[$window]);
        }
    }

    public function testUnknownWindowThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->analytics->pageViews(null, "bogus");
    }
}
