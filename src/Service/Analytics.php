<?php

namespace Base\Service;

use Base\Repository\Analytics\PageViewRepository;
use Base\Repository\Analytics\VisitRepository;
use Base\Entity\Analytics\PageView;
use Base\Entity\Analytics\Visit;
use Base\Service\Analytics\UserAgentClassifier;

/**
 * Single entry point for this app's traffic counters: page views (a raw
 * hit count per path) plus two independent unique-subject counters -
 * anonymous "visitors" (cookie-identified, see the consent note on
 * trackVisitor()) and authenticated "users" (Base\Entity\User ids) - both
 * sliceable by the same time windows (today/24h, 7 days, 30 days, all
 * time) because they're built on the exact same underlying mechanism
 * (Base\Entity\Analytics\Visit's daily presence rows), just keyed
 * differently. Storage/aggregation strategy lives in the two repositories;
 * this class is the only thing application code (a request subscriber, an
 * admin sidebar widget, ...) needs to know about.
 */
class Analytics
{
    public function __construct(
        private readonly PageViewRepository $pageViews,
        private readonly VisitRepository $visits,
        private readonly UserAgentClassifier $userAgentClassifier,
    ) {
    }

    /**
     * Call once per trackable request. $visitorId is the ANALYTICS/
     * VISITOR_ID cookie value if present - deliberately never generated or
     * set here: that cookie is written client-side via Cookie.set("ANALYTICS",
     * "VISITOR_ID", ...) (see @glitchr/cookie), which silently no-ops
     * without consent. A visit with no visitor cookie still counts toward
     * pageViews() (an anonymous hit, no identity involved) but never
     * toward uniqueVisitors() - consent is what turns a hit into a
     * de-duplicated visitor, not the other way around. $userId has no such
     * gating: it's the already-authenticated session's own account.
     *
     * $userAgent decides which PageView::SOURCE_* bucket the hit lands in
     * (via UserAgentClassifier) - null (the default, used by callers that
     * never had an HTTP request to read a header from, e.g. this class's
     * own test suite) is treated as SOURCE_HUMAN rather than run through
     * the classifier, which would otherwise classify a truly empty/missing
     * User-Agent as SOURCE_BOT. A hit classified as anything other than
     * SOURCE_HUMAN never reaches the visitor/user presence tables below -
     * a crawler's stray cookie or session should never count as a real
     * unique visitor.
     */
    public function track(string $path, ?string $visitorId = null, ?string $userId = null, ?string $userAgent = null): void
    {
        $today = new \DateTimeImmutable("today");
        $source = $userAgent !== null ? $this->userAgentClassifier->classify($userAgent) : PageView::SOURCE_HUMAN;

        $this->pageViews->incrementView($path, $today, $source);

        if ($source !== PageView::SOURCE_HUMAN) {
            return;
        }

        if ($visitorId !== null && $visitorId !== "") {
            $this->visits->recordPresence(Visit::TYPE_VISITOR, $visitorId, $today);
        }
        if ($userId !== null && $userId !== "") {
            $this->visits->recordPresence(Visit::TYPE_USER, $userId, $today);
        }
    }

    /**
     * Total hits. $path null = every page (site-wide). $source null = every
     * source combined (human + bot + ai); pass a PageView::SOURCE_*
     * constant to see just that bucket's hits.
     */
    public function pageViews(?string $path = null, ?string $window = null, ?string $source = null): int
    {
        return $this->pageViews->countViews($path, self::resolveWindow($window), $source);
    }

    /**
     * Distinct anonymous (cookie-consented) visitors.
     */
    public function uniqueVisitors(?string $window = null): int
    {
        return $this->visits->countUnique(Visit::TYPE_VISITOR, self::resolveWindow($window));
    }

    /**
     * Distinct authenticated user accounts active in the window.
     */
    public function uniqueUsers(?string $window = null): int
    {
        return $this->visits->countUnique(Visit::TYPE_USER, self::resolveWindow($window));
    }

    /**
     * The set of numbers an admin sidebar widget wants at a glance -
     * every counter across every standard window in one round trip's
     * worth of queries (9 small aggregate queries, all against the tiny
     * daily-rollup tables - cheap regardless of how much traffic has
     * accumulated behind them).
     */
    public function summary(): array
    {
        $windows = ["today", "7d", "30d", "all"];

        $summary = [];
        foreach ($windows as $window) {
            $summary[$window] = [
                "pageViews" => $this->pageViews(null, $window),
                "pageViewsHuman" => $this->pageViews(null, $window, PageView::SOURCE_HUMAN),
                "pageViewsBot" => $this->pageViews(null, $window, PageView::SOURCE_BOT),
                "pageViewsAi" => $this->pageViews(null, $window, PageView::SOURCE_AI),
                "uniqueVisitors" => $this->uniqueVisitors($window),
                "uniqueUsers" => $this->uniqueUsers($window),
            ];
        }

        return $summary;
    }

    /**
     * Day-by-day series for the last $days calendar days (oldest first,
     * today included) - the raw data a dashboard trend chart plots
     * directly. Unlike summary()'s window totals, a day with genuinely
     * zero activity is filled in as 0 rather than omitted, so a chart
     * never has to guess at a gap in the x-axis.
     *
     * $days = null means "all time": since the earliest day anything was
     * ever tracked (today, if nothing ever was - a 1-day series rather
     * than an empty one, so callers never have to special-case "no data
     * yet" separately from "no data in this window").
     *
     * $path null means every page summed together (the original, site-
     * wide semantics); pass an exact path to scope the PAGE-VIEW columns
     * to one page instead (e.g. a single Article's own traffic trend).
     * uniqueVisitors/uniqueUsers stay SITE-WIDE regardless of $path - the
     * underlying presence records (Visit) have no path of their own at
     * all (see Visit's own docblock), so "unique visitors to this one
     * page" isn't a question this data model can answer yet. A $path-
     * scoped caller should simply not read those two columns rather than
     * this method fabricating a page-specific number it can't back up.
     *
     * @return array<int, array{date: string, pageViews: int, pageViewsHuman: int, pageViewsBot: int, pageViewsAi: int, uniqueVisitors: int, uniqueUsers: int}>
     */
    public function dailyBreakdown(?int $days = 14, ?string $path = null): array
    {
        if (null === $days) {
            $since = $this->pageViews->earliestDate() ?? new \DateTimeImmutable("today");
            $days = (int) $since->diff(new \DateTimeImmutable("today"))->format("%a") + 1;
        } else {
            $since = new \DateTimeImmutable(($days - 1) . " days ago midnight");
        }

        $pageViews = $this->pageViews->dailyBreakdown($since, $path);
        $pageViewsBySource = $this->pageViews->dailyBreakdownBySource($since, $path);
        $visitors = $this->visits->dailyBreakdown(Visit::TYPE_VISITOR, $since);
        $users = $this->visits->dailyBreakdown(Visit::TYPE_USER, $since);

        $emptySource = [PageView::SOURCE_HUMAN => 0, PageView::SOURCE_BOT => 0, PageView::SOURCE_AI => 0];

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $since->modify("+{$i} days")->format("Y-m-d");
            $bySource = $pageViewsBySource[$date] ?? $emptySource;
            $series[] = [
                "date" => $date,
                "pageViews" => $pageViews[$date] ?? 0,
                "pageViewsHuman" => $bySource[PageView::SOURCE_HUMAN],
                "pageViewsBot" => $bySource[PageView::SOURCE_BOT],
                "pageViewsAi" => $bySource[PageView::SOURCE_AI],
                "uniqueVisitors" => $visitors[$date] ?? 0,
                "uniqueUsers" => $users[$date] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * This 7-day window vs the 7 days before it, as a signed percentage
     * per counter - the "up/down vs last week" badge a dashboard trend
     * card needs. Reuses dailyBreakdown(14) rather than four more window
     * queries: the same 14 rows already answer both halves.
     *
     * @return array{pageViews: ?float, pageViewsHuman: ?float, pageViewsBot: ?float, pageViewsAi: ?float, uniqueVisitors: ?float, uniqueUsers: ?float}
     *         null when the prior week was zero (no meaningful percentage to show)
     */
    public function weekOverWeekChange(): array
    {
        $series = $this->dailyBreakdown(14);
        $previousWeek = \array_slice($series, 0, 7);
        $thisWeek = \array_slice($series, 7, 7);

        $sum = fn(array $days, string $key) => array_sum(array_column($days, $key));

        $result = [];
        foreach (["pageViews", "pageViewsHuman", "pageViewsBot", "pageViewsAi", "uniqueVisitors", "uniqueUsers"] as $key) {
            $previous = $sum($previousWeek, $key);
            $current = $sum($thisWeek, $key);
            $result[$key] = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null;
        }

        return $result;
    }

    /**
     * "today"/"24h" (kept as two spellings of the same thing - this is a
     * calendar-day bucket, not a true rolling 24h window: the daily-rollup
     * storage can't distinguish "3am today" from "11pm today" any finer
     * than that, which is the standard, deliberate trade-off of a daily-
     * aggregate design over a raw event log), "7d", "30d", null/"all".
     */
    private static function resolveWindow(?string $window): ?\DateTimeImmutable
    {
        return match ($window) {
            null, "all" => null,
            "today", "24h" => new \DateTimeImmutable("today"),
            "7d" => new \DateTimeImmutable("-6 days"),
            "30d" => new \DateTimeImmutable("-29 days"),
            default => throw new \InvalidArgumentException("Unknown analytics window: \"{$window}\" (expected one of: today, 24h, 7d, 30d, all)"),
        };
    }
}
