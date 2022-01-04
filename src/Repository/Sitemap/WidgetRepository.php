<?php

namespace Base\Repository\Sitemap;

use Base\Entity\Sitemap\Widget;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Widget|null find($id, $lockMode = null, $lockVersion = null)
 * @method Widget|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Widget[]    findAll()
 * @method Widget[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class WidgetRepository extends ServiceEntityRepository
{
    public function findOneByUuid(string $uuid)
    {
        return $this->createQueryBuilder("w")
            ->where('w.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->leftJoin('w.translations', 't')
            ->addSelect("t")
            ->getQuery()->getOneOrNullResult();
    }
}
