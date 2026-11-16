<?php

namespace Base\Repository\Hours;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Hours\ScopedWeek;
use Base\Repository\Hours\ScopedWeeks;

/**
 * @extends ServiceEntityRepository<ScopedWeek>
 *
 * @method ScopedWeek|null find($id)
 * @method ScopedWeek[]    findAll()
 */
class ScopedWeekRepository extends ServiceEntityRepository implements ScopedWeeks
{
    public function ofScope(string $scope): ?ScopedWeek
    {
        return $this->findOneBy(['scope' => $scope]);
    }

    /** @return array<int, array<array{0: string, 1: string}>>|null the place's week, all seven days; null: it follows the site's */
    public function week(string $scope): ?array
    {
        return $this->ofScope($scope)?->getWeek();
    }

    /** @param array<int, array<array{0: string, 1: string}>> $week ISO day => hours */
    public function save(string $scope, array $week): ScopedWeek
    {
        $row = $this->ofScope($scope);
        $row ? $row->setWeek($week) : $row = new \App\Entity\Hours\ScopedWeek($scope, $week);

        $this->persist($row, true);

        return $row;
    }

    /** Back to the site's week. */
    public function forget(string $scope): void
    {
        if ($row = $this->ofScope($scope)) {
            $this->remove($row, true);
        }
    }
}
