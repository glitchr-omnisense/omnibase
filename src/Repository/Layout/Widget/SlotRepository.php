<?php

namespace Base\Repository\Layout\Widget;

use Base\Entity\Layout\Widget\Slot;
use Base\Repository\Layout\WidgetRepository;

/**
 * @method Slot|null find($id, $lockMode = null, $lockVersion = null)
 * @method Slot|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Slot[]    findAll()
 * @method Slot[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class SlotRepository extends WidgetRepository implements Slots
{
    /** Slots (step R28): WidgetProvider's finders, the result cache unless $cached is false. */
    public function oneByPath(string $path, bool $cached = true): ?Slot
    {
        return $cached ? $this->cacheOneByPath($path) : $this->findOneByPath($path);
    }

    // Attempt to cache
    // public function findOneByPath(string $path)
    // {
    //     return $this->createQueryBuilder("s")
    //         ->where('s.path = :path')
    //         ->setParameter('path', $path)
    //         ->leftJoin('s.widgets', 'w')
    //         ->leftJoin('s.translations', 't')
    //         ->addSelect("w", "t")
    //         ->getQuery()->enableResultCache()->getOneOrNullResult();
    // }
}
