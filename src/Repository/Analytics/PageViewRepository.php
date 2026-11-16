<?php

namespace Base\Repository\Analytics;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Database\Repository\Finder\Aggregate;
use Base\Database\Repository\Finder\Change;
use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Group;
use Base\Database\Repository\Finder\Parser;
use Base\Entity\Analytics\PageView;
use Base\Repository\Analytics\PageViews;

/**
 * @extends ServiceEntityRepository<PageView>
 */
class PageViewRepository extends ServiceEntityRepository implements PageViews
{
    /**
     * Atomic increment, not a load-modify-flush cycle - two concurrent
     * requests hitting the same page the same HOUR must both count, not
     * race and silently drop one. Native upsert rather than an
     * entity round-trip: the hour's row counted up in the database (updateBy() with
     * Change::add()), inserted when there is none yet (a concurrent insert lost:
     * counted up again). $source is part of the unique key
     * now (see PageView's docblock), so human/bot/ai hits on the same page
     * the same hour land in three separate rows rather than one blended
     * count.
     *
     * $date is bucketed down to the top of its hour here (minutes/seconds
     * zeroed) rather than trusting every caller to already pass an
     * hour-aligned value - Analytics::track() passes the raw "now", not
     * something pre-bucketed, same as it used to pass a raw "today" before
     * PageView moved from daily to hourly grain.
     */
    public function incrementView(string $path, \DateTimeImmutable $date, string $source = PageView::SOURCE_HUMAN): void
    {
        $key = ['path' => mb_substr($path, 0, 255), 'date' => self::hour($date), 'source' => $source];
        if ($this->updateBy($key, ['views' => Change::add(1)]) > 0) {
            return;
        }
        try {
            $this->sql('INSERT INTO {PageView} ({PageView.path}, {PageView.date}, {PageView.source}, {PageView.views}) VALUES (?, ?, ?, 1)', [$key['path'], $key['date'], $source]);
        } catch (\Throwable $exception) {
            // Another request wrote the hour's row in between: count on it.
            if (0 === $this->updateBy($key, ['views' => Change::add(1)])) {
                throw $exception;
            }
        }
    }

    /**
     * Sums the daily rows in range - $path null means every page (a
     * site-wide total), $since null means all-time (no lower bound),
     * $source null means every source (human + bot + ai combined, the
     * pre-existing "total hits" semantics); pass a PageView::SOURCE_*
     * constant to see just that bucket.
     */
    public function countViews(?string $path = null, ?\DateTimeImmutable $since = null, ?string $source = null): int
    {
        $criteria = array_filter(["path" => $path, "source" => $source], static fn ($value) => null !== $value);
        // SUM(views), null without a row.
        $views = null === $since ? $this->sumOfViewsBy($criteria) : $this->sumOfViewsByDateGreaterEqualTo($since, $criteria);

        return (int) ($views ?? 0);
    }

    /**
     * The earliest recorded DAY, site-wide - what "all time" actually
     * means for dailyBreakdown()'s $days=null case (there's no data
     * before this, so generating a series further back would just be
     * rows of zeroes). Null if nothing has ever been tracked.
     *
     * Truncated to midnight even though the underlying column is now
     * hour-grained - Analytics::dailyBreakdown()'s "all time" day-count
     * (a plain calendar diff against today) would be thrown off by
     * whatever hour the earliest row happens to carry otherwise.
     */
    public function earliestDate(): ?\DateTimeImmutable
    {
        $date = $this->minOfDate();

        // Stored in UTC, so read back as UTC.
        return $date ? new \DateTimeImmutable(substr((string) $date, 0, 10), new \DateTimeZone("UTC")) : null;
    }

    /**
     * One row per calendar day in range, oldest first - the raw series a
     * dashboard trend chart plots directly, no client-side date bucketing
     * needed. $path null means every page summed together (the original,
     * site-wide semantics); pass an exact path to scope the series to one
     * page instead (e.g. a single Article's own traffic trend).
     *
     * A statement of each persistence (native SQL) because this needs to fold the table's own hourly rows back
     * down to one row per DAY via DATE(date) - the finder language has no DATE()
     * grouping, and a GROUP BY pv.date
     * here would silently return one row per HOUR instead of per day.
     *
     * @return array<string, int> date (Y-m-d) => views
     */
    /**
     * @param string|string[]|null $path a single path (exact match), a list
     *        of paths (any-of match - e.g. every Article's own __toLink(),
     *        for a whole-class rollup rather than one instance), or null
     *        for site-wide
     */
    public function dailyBreakdown(\DateTimeImmutable $since, string|array|null $path = null): array
    {
        $breakdown = [];
        foreach ($this->findValuesBy(['date' => Aggregate::day('date'), 'views' => Aggregate::sum('views')], self::window($since, $path), ['date' => 'ASC']) as $row) {
            $breakdown[(string) $row['date']] = (int) $row['views'];
        }

        return $breakdown;
    }

    /**
     * Same shape/semantics as dailyBreakdown(), but grouped by source too -
     * one nested array per day instead of one flat total, so a chart can
     * plot "human traffic" and "bot/AI traffic" as separate lines instead
     * of one number that hides the split. Every PageView::SOURCE_* key is
     * always present per day (0 when that source had no hits that day),
     * same "never make the caller guess at a missing key" guarantee
     * dailyBreakdown()/Analytics::dailyBreakdown() already give callers.
     *
     * $path null means every page (site-wide); pass an exact path to scope
     * this to one page instead, same convention as dailyBreakdown().
     *
     * @return array<string, array<string, int>> date (Y-m-d) => [source => views]
     */
    /**
     * @param string|string[]|null $path see dailyBreakdown()'s own docblock
     */
    public function dailyBreakdownBySource(\DateTimeImmutable $since, string|array|null $path = null): array
    {
        $breakdown = [];
        foreach ($this->findValuesBy(['date' => Aggregate::day('date'), 'source', 'views' => Aggregate::sum('views')], self::window($since, $path), ['date' => 'ASC']) as $row) {
            $breakdown[(string) $row['date']] ??= array_fill_keys([PageView::SOURCE_HUMAN, PageView::SOURCE_BOT, PageView::SOURCE_AI], 0);
            $breakdown[(string) $row['date']][$row['source']] = (int) $row['views'];
        }

        return $breakdown;
    }

    /**
     * One row per HOUR in range, oldest first - the same idea as
     * dailyBreakdown() one grain finer, for the one range ("today") short
     * enough that an hour-by-hour curve is actually legible. No zero-fill
     * here (unlike Analytics::hourlyBreakdown(), which is the one that
     * fills in every hour up to now) - this stays a thin raw-rows read,
     * same division of responsibility dailyBreakdown() already has with
     * Analytics::dailyBreakdown().
     *
     * @return array<string, int> hour ("Y-m-d H:i:s") => views
     */
    /**
     * @param string|string[]|null $path see dailyBreakdown()'s own docblock
     */
    public function hourlyBreakdown(\DateTimeImmutable $since, string|array|null $path = null): array
    {
        $breakdown = [];
        foreach ($this->findValuesBy(['date', 'views' => Aggregate::sum('views')], self::window($since, $path), ['date' => 'ASC']) as $row) {
            $breakdown[(string) $row['date']] = (int) $row['views'];
        }

        return $breakdown;
    }

    /**
     * Same shape/semantics as hourlyBreakdown(), but grouped by source too
     * - the hourly counterpart to dailyBreakdownBySource().
     *
     * @return array<string, array<string, int>> hour ("Y-m-d H:i:s") => [source => views]
     */
    /**
     * @param string|string[]|null $path see dailyBreakdown()'s own docblock
     */
    public function hourlyBreakdownBySource(\DateTimeImmutable $since, string|array|null $path = null): array
    {
        $breakdown = [];
        foreach ($this->findValuesBy(['date', 'source', 'views' => Aggregate::sum('views')], self::window($since, $path), ['date' => 'ASC']) as $row) {
            $breakdown[(string) $row['date']] ??= array_fill_keys([PageView::SOURCE_HUMAN, PageView::SOURCE_BOT, PageView::SOURCE_AI], 0);
            $breakdown[(string) $row['date']][$row['source']] = (int) $row['views'];
        }

        return $breakdown;
    }

    /** The hour a moment falls in, in UTC: the rows are counted per hour. */
    private static function hour(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $date = $date->setTimezone(new \DateTimeZone('UTC'));

        return $date->setTime((int) $date->format('H'), 0, 0);
    }

    /**
     * Since a moment (in UTC), on a page or a list of pages (an empty list: none).
     *
     * @param string|list<string>|null $path
     *
     * @return array<int|string, mixed>
     */
    private static function window(\DateTimeImmutable $since, string|array|null $path): array
    {
        $window = [Group::of(Parser::SEPARATOR_AND, Criterion::of('date', $since->setTimezone(new \DateTimeZone('UTC')), Parser::OPTION_GREATER_EQUAL))];

        return null === $path ? $window : [...$window, 'path' => $path];
    }
}
