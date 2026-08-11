<?php

namespace Base\Repository\Analytics;

use Base\Entity\Analytics\Visit;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Visit>
 */
class VisitRepository extends ServiceEntityRepository
{
    /**
     * Insert-if-absent, not insert-or-update: a presence row only ever
     * needs to exist once per (hour, subject) - a second sighting the same
     * hour is a silent no-op, not an update. INSERT IGNORE (rather than a
     * SELECT-then-INSERT round trip) makes that race-safe under concurrent
     * requests from the same subject too.
     *
     * $date is bucketed down to the top of its hour here, same reasoning
     * as PageViewRepository::incrementView()'s own bucketing.
     */
    public function recordPresence(string $subjectType, string $subjectId, \DateTimeImmutable $date): void
    {
        $table = $this->getClassMetadata()->getTableName();
        $connection = $this->getEntityManager()->getConnection();
        $hour = $date->setTime((int) $date->format("H"), 0, 0);

        $connection->executeStatement(
            "INSERT IGNORE INTO {$table} (date, subject_type, subject_id) VALUES (:date, :type, :id)",
            ["date" => $hour->format("Y-m-d H:i:s"), "type" => $subjectType, "id" => mb_substr($subjectId, 0, 64)],
        );
    }

    /**
     * True unique count over the window (not an approximation): each
     * subject contributes at most one row per day it was seen, so
     * COUNT(DISTINCT subject_id) across the date range never double-counts
     * a subject who returned on multiple days within it.
     */
    public function countUnique(string $subjectType, ?\DateTimeImmutable $since = null): int
    {
        $qb = $this->createQueryBuilder("v")
            ->select("COUNT(DISTINCT v.subjectId)")
            ->andWhere("v.subjectType = :type")
            ->setParameter("type", $subjectType);

        if ($since !== null) {
            $qb->andWhere("v.date >= :since")->setParameter("since", $since);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * One row per calendar day in range - unlike countUnique() (a single
     * total over the whole window), a subject seen on multiple days
     * within range legitimately contributes to EACH of those days here;
     * that's the correct semantics for "how many distinct subjects were
     * active on day X", which is what a trend chart needs.
     *
     * Raw SQL, same reason as PageViewRepository::dailyBreakdown(): folding
     * this table's own hourly rows back down to one row per DAY needs
     * DATE(date), which DQL has no built-in function for.
     *
     * @return array<string, int> date (Y-m-d) => distinct subject count
     */
    public function dailyBreakdown(string $subjectType, \DateTimeImmutable $since): array
    {
        $table = $this->getClassMetadata()->getTableName();
        $connection = $this->getEntityManager()->getConnection();

        $rows = $connection->fetchAllAssociative(
            "SELECT DATE(date) AS date, COUNT(DISTINCT subject_id) AS count FROM {$table}
             WHERE subject_type = :type AND date >= :since
             GROUP BY DATE(date) ORDER BY DATE(date) ASC",
            ["type" => $subjectType, "since" => $since->format("Y-m-d H:i:s")],
        );

        $breakdown = [];
        foreach ($rows as $row) {
            $breakdown[$row["date"]] = (int) $row["count"];
        }

        return $breakdown;
    }

    /**
     * One row per HOUR in range - the hourly counterpart to
     * dailyBreakdown(), for the one range ("today") an hour-by-hour curve
     * is actually legible for. No zero-fill here, same division of
     * responsibility as PageViewRepository::hourlyBreakdown() vs
     * Analytics::hourlyBreakdown().
     *
     * @return array<string, int> hour ("Y-m-d H:i:s") => distinct subject count
     */
    public function hourlyBreakdown(string $subjectType, \DateTimeImmutable $since): array
    {
        $table = $this->getClassMetadata()->getTableName();
        $connection = $this->getEntityManager()->getConnection();

        $rows = $connection->fetchAllAssociative(
            "SELECT date, COUNT(DISTINCT subject_id) AS count FROM {$table}
             WHERE subject_type = :type AND date >= :since
             GROUP BY date ORDER BY date ASC",
            ["type" => $subjectType, "since" => $since->format("Y-m-d H:i:s")],
        );

        $breakdown = [];
        foreach ($rows as $row) {
            $breakdown[$row["date"]] = (int) $row["count"];
        }

        return $breakdown;
    }
}
