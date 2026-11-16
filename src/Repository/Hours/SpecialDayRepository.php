<?php

namespace Base\Repository\Hours;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Hours\SpecialDay;
use Base\Repository\Hours\SpecialDays;

/**
 * @extends ServiceEntityRepository<SpecialDay>
 *
 * @method SpecialDay|null find($id)
 * @method SpecialDay[]    findAll()
 */
class SpecialDayRepository extends ServiceEntityRepository implements SpecialDays
{
    /**
     * @param string|null $scope the place's own; null: the whole site's
     *
     * @return SpecialDay[] those not over by $from, soonest first
     */
    public function upcoming(\DateTimeInterface $from, int $limit = 50, ?string $scope = null): array
    {
        // The days are kept as Y-m-d text, which sorts and compares as the dates do.
        $from = $from->format('Y-m-d');

        return (null === $scope
            ? $this->findByEndsOnGreaterEqualToAndScopeNull($from, [], ['startsOn' => 'ASC'], $limit)
            : $this->findByEndsOnGreaterEqualTo($from, ['scope' => $scope], ['startsOn' => 'ASC'], $limit)
        )->getResult();
    }
}
