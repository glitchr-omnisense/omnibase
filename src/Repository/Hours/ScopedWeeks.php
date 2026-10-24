<?php

namespace Base\Repository\Hours;

use Base\Database\Repository\Entities;
use Base\Entity\Hours\ScopedWeek;

/**
 * The opening weeks of the places that have their own (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): ScopedWeekRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<ScopedWeek>
 */
interface ScopedWeeks extends Entities
{
    public function ofScope(string $scope): ?ScopedWeek;

    /** @return array<mixed>|null */
    public function week(string $scope): ?array;

    /** @param array<mixed> $week */
    public function save(string $scope, array $week): ScopedWeek;

    public function forget(string $scope): void;
}
