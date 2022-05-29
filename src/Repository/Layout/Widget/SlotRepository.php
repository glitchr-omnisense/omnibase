<?php

namespace Base\Repository\Layout\Widget;

use Base\Entity\Layout\Widget\Slot;
use Base\Repository\Layout\WidgetRepository;

/**
 * @method Slot|null find($id, $lockMode = null, $lockVersion = null)
 * @method Slot|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Slot[]    findAll()
 * @method Slot[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class SlotRepository extends WidgetRepository
{
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
