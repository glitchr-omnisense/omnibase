<?php

namespace Base\Repository\Analytics;

use Base\Entity\Analytics\PageView;
use Base\Database\Repository\ServiceEntityRepository;

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
     * entity round-trip.
     */
    public function incrementView(string $path, \DateTimeImmutable $date): void
    {
        $table = $this->getClassMetadata()->getTableName();
        $connection = $this->getEntityManager()->getConnection();

        $connection->executeStatement(
            "INSERT INTO {$table} (path, date, views) VALUES (:path, :date, 1)
             ON DUPLICATE KEY UPDATE views = views + 1",
            ["path" => mb_substr($path, 0, 255), "date" => $date->format("Y-m-d")],
        );
    }

    /**
     * Sums the daily rows in range - $path null means every page (a
     * site-wide total), $since null means all-time (no lower bound).
     */
    public function countViews(?string $path = null, ?\DateTimeImmutable $since = null): int
    {
        $qb = $this->createQueryBuilder("pv")
            ->select("COALESCE(SUM(pv.views), 0)");

        if ($path !== null) {
            $qb->andWhere("pv.path = :path")->setParameter("path", $path);
        }
        if ($since !== null) {
            $qb->andWhere("pv.date >= :since")->setParameter("since", $since);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
