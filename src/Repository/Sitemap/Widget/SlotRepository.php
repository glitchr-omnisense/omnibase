<?php

namespace Base\Repository\Sitemap\Widget;

use Base\Entity\Sitemap\Widget\Slot;
use Base\Repository\Sitemap\WidgetRepository;

/**
 * @method Slot|null find($id, $lockMode = null, $lockVersion = null)
 * @method Slot|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Slot[]    findAll()
 * @method Slot[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class SlotRepository extends WidgetRepository
{
    public function findOneByPath(string $path) 
    {
        dump($this->createQueryBuilder("s")
        ->where('s.path = :path')
        ->setParameter('path', $path)
        ->leftJoin('s.widgets', 'w')
        ->leftJoin('s.translations', 't')
        ->addSelect("w", "t")
        ->getQuery());
        
        return $this->createQueryBuilder("s")
            ->where('s.path = :path')
            ->setParameter('path', $path)
            ->leftJoin('s.widgets', 'w')
            ->leftJoin('s.translations', 't')
            ->addSelect("w", "t")
            ->getQuery()->getOneOrNullResult();
    }
}
