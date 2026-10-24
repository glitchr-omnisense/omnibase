<?php

namespace Base\Repository\Hours;

use Base\Database\Repository\Entities;
use Base\Entity\Hours\WeekDayHours;

/**
 * The site's opening week, day by day (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): WeekDayHoursRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<WeekDayHours>
 */
interface WeekDays extends Entities
{
    /** @return array<mixed> */
    public function week(): array;

    /** @param array<mixed> $week */
    public function save(array $week): void;
}
