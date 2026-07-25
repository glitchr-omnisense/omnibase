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
     * needs to exist once per (day, subject) - a second sighting the same
     * day is a silent no-op, not an update. INSERT IGNORE (rather than a
     * SELECT-then-INSERT round trip) makes that race-safe under concurrent
     * requests from the same subject too.
     */
    public function recordPresence(string $subjectType, string $subjectId, \DateTimeImmutable $date): void
    {
        $table = $this->getClassMetadata()->getTableName();
        $connection = $this->getEntityManager()->getConnection();

        $connection->executeStatement(
            "INSERT IGNORE INTO {$table} (date, subject_type, subject_id) VALUES (:date, :type, :id)",
            ["date" => $date->format("Y-m-d"), "type" => $subjectType, "id" => mb_substr($subjectId, 0, 64)],
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
     * @return array<string, int> date (Y-m-d) => distinct subject count
     */
    public function dailyBreakdown(string $subjectType, \DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder("v")
            ->select("v.date AS date, COUNT(DISTINCT v.subjectId) AS count")
            ->andWhere("v.subjectType = :type")
            ->andWhere("v.date >= :since")
            ->setParameter("type", $subjectType)
            ->setParameter("since", $since)
            ->groupBy("v.date")
            ->orderBy("v.date", "ASC")
            ->getQuery()
            ->getArrayResult();

        $breakdown = [];
        foreach ($rows as $row) {
            $date = $row["date"] instanceof \DateTimeInterface ? $row["date"]->format("Y-m-d") : (string) $row["date"];
            $breakdown[$date] = (int) $row["count"];
        }

        return $breakdown;
    }
}
