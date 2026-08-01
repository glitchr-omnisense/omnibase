<?php

namespace Base\Repository\Analytics;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Analytics\PageView;

/**
 * @extends ServiceEntityRepository<PageView>
 */
class PageViewRepository extends ServiceEntityRepository
{
    /**
     * Atomic increment, not a load-modify-flush cycle - two concurrent
     * requests hitting the same page the same day must both count, not
     * race and silently drop one. Native upsert (MySQL/MariaDB-specific,
     * same DB family as the rest of this app) rather than a Doctrine
     * entity round-trip. $source is part of the unique key now (see
     * PageView's docblock), so human/bot/ai hits on the same page the same
     * day land in three separate rows rather than one blended count.
     */
    public function incrementView(string $path, \DateTimeImmutable $date, string $source = PageView::SOURCE_HUMAN): void
    {
        $table = $this->getClassMetadata()->getTableName();
        $connection = $this->getEntityManager()->getConnection();

        $connection->executeStatement(
            "INSERT INTO {$table} (path, date, source, views) VALUES (:path, :date, :source, 1)
             ON DUPLICATE KEY UPDATE views = views + 1",
            ["path" => mb_substr($path, 0, 255), "date" => $date->format("Y-m-d"), "source" => $source],
        );
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
        $qb = $this->createQueryBuilder("pv")
            ->select("COALESCE(SUM(pv.views), 0)");

        if ($path !== null) {
            $qb->andWhere("pv.path = :path")->setParameter("path", $path);
        }
        if ($since !== null) {
            $qb->andWhere("pv.date >= :since")->setParameter("since", $since);
        }
        if ($source !== null) {
            $qb->andWhere("pv.source = :source")->setParameter("source", $source);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * The earliest recorded day, site-wide - what "all time" actually
     * means for dailyBreakdown()'s $days=null case (there's no data
     * before this, so generating a series further back would just be
     * rows of zeroes). Null if nothing has ever been tracked.
     */
    public function earliestDate(): ?\DateTimeImmutable
    {
        $date = $this->createQueryBuilder("pv")
            ->select("MIN(pv.date)")
            ->getQuery()
            ->getSingleScalarResult();

        return $date ? new \DateTimeImmutable($date) : null;
    }

    /**
     * One row per calendar day in range, oldest first - the raw series a
     * dashboard trend chart plots directly, no client-side date bucketing
     * needed. $path null means every page summed together (the original,
     * site-wide semantics); pass an exact path to scope the series to one
     * page instead (e.g. a single Article's own traffic trend).
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
        $qb = $this->createQueryBuilder("pv")
            ->select("pv.date AS date, SUM(pv.views) AS views")
            ->andWhere("pv.date >= :since")
            ->setParameter("since", $since)
            ->groupBy("pv.date")
            ->orderBy("pv.date", "ASC");

        if (\is_array($path)) {
            $qb->andWhere("pv.path IN (:paths)")->setParameter("paths", $path);
        } elseif ($path !== null) {
            $qb->andWhere("pv.path = :path")->setParameter("path", $path);
        }

        $rows = $qb->getQuery()->getArrayResult();

        $breakdown = [];
        foreach ($rows as $row) {
            $date = $row["date"] instanceof \DateTimeInterface ? $row["date"]->format("Y-m-d") : (string) $row["date"];
            $breakdown[$date] = (int) $row["views"];
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
        $qb = $this->createQueryBuilder("pv")
            ->select("pv.date AS date, pv.source AS source, SUM(pv.views) AS views")
            ->andWhere("pv.date >= :since")
            ->setParameter("since", $since)
            ->groupBy("pv.date")
            ->addGroupBy("pv.source")
            ->orderBy("pv.date", "ASC");

        if (\is_array($path)) {
            $qb->andWhere("pv.path IN (:paths)")->setParameter("paths", $path);
        } elseif ($path !== null) {
            $qb->andWhere("pv.path = :path")->setParameter("path", $path);
        }

        $rows = $qb->getQuery()->getArrayResult();

        $sources = [PageView::SOURCE_HUMAN, PageView::SOURCE_BOT, PageView::SOURCE_AI];

        $breakdown = [];
        foreach ($rows as $row) {
            $date = $row["date"] instanceof \DateTimeInterface ? $row["date"]->format("Y-m-d") : (string) $row["date"];
            $breakdown[$date] ??= array_fill_keys($sources, 0);
            $breakdown[$date][$row["source"]] = (int) $row["views"];
        }

        return $breakdown;
    }
}
