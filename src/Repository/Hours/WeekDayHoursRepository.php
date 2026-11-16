<?php

namespace Base\Repository\Hours;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Hours\WeekDayHours;
use Base\Repository\Hours\WeekDays;

/**
 * @extends ServiceEntityRepository<WeekDayHours>
 *
 * @method WeekDayHours|null find($id)
 * @method WeekDayHours[]    findAll()
 */
class WeekDayHoursRepository extends ServiceEntityRepository implements WeekDays
{
    /** @return array<int, array<array{0: string, 1: string}>> ISO day => hours; empty when never set */
    public function week(): array
    {
        $week = [];
        foreach ($this->findAll() as $day) {
            $week[$day->getWeekday()] = $day->getHours();
        }
        ksort($week);

        return $week;
    }

    /** @param array<int, array<array{0: string, 1: string}>> $week all seven days, ISO day => hours */
    public function save(array $week): void
    {
        // By weekday, the entity's key.
        $days = [];
        foreach ($this->findAll() as $day) {
            $days[$day->getWeekday()] = $day;
        }

        for ($n = 1; $n <= 7; ++$n) {
            $day = $days[$n] ?? new \App\Entity\Hours\WeekDayHours($n);
            $day->setHours($week[$n] ?? []);
            $this->persist($day);
        }
        $this->flush();
    }
}
