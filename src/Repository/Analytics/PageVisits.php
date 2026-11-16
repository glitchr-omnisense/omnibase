<?php

namespace Base\Repository\Analytics;

use Base\Database\Repository\Entities;
use Base\Entity\Analytics\PageVisit;

/**
 * The presence on each page, by day and hour (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): PageVisitRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<object> of PageVisit (its repository declares ServiceEntityRepository<PageVisit>)
 */
interface PageVisits extends Entities
{
    public function recordPresence(string $path, string $subjectType, string $subjectId, \DateTimeImmutable $date): void;

    /** @param string|list<string>|null $path */
    public function countUnique(?\DateTimeImmutable $since = null, string|array|null $path = null): int;

    /**
     * @param string|list<string>|null $path
     *
     * @return array<mixed>
     */
    public function dailyBreakdown(\DateTimeImmutable $since, string|array|null $path = null): array;

    /**
     * @param string|list<string>|null $path
     *
     * @return array<mixed>
     */
    public function hourlyBreakdown(\DateTimeImmutable $since, string|array|null $path = null): array;
}
