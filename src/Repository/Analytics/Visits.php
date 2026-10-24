<?php

namespace Base\Repository\Analytics;

use Base\Database\Repository\Entities;
use Base\Entity\Analytics\Visit;

/**
 * The visitors' and members' presence, by day and hour (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): VisitRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<object> of Visit (its repository declares ServiceEntityRepository<Visit>)
 */
interface Visits extends Entities
{
    public function recordPresence(string $subjectType, string $subjectId, \DateTimeImmutable $date): void;

    public function countUnique(string $subjectType, ?\DateTimeImmutable $since = null): int;

    /** @return array<mixed> */
    public function returning(string $subjectType, \DateTimeImmutable $previousSince, \DateTimeImmutable $currentSince): array;

    /** @return array<mixed> */
    public function dailyBreakdown(string $subjectType, \DateTimeImmutable $since): array;

    /** @return array<mixed> */
    public function hourlyBreakdown(string $subjectType, \DateTimeImmutable $since): array;
}
