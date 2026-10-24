<?php

namespace Base\Repository\Hours;

use Base\Database\Repository\Entities;
use Base\Entity\Hours\SpecialDay;

/**
 * The special days (closures, other hours) (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): SpecialDayRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<SpecialDay>
 */
interface SpecialDays extends Entities
{
    /** @return list<SpecialDay> from $from on, the nearest first */
    public function upcoming(\DateTimeInterface $from, int $limit = 50, ?string $scope = null): array;
}
