<?php

namespace App\Repository\Sitemap;

use App\Entity\Sitemap\ImageCrop;
use Base\Database\Repository\ServiceEntityRepository;

use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ImageCrop|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageCrop|null findOneBy(array $criteria, array $orderBy = null)
 * @method ImageCrop[]    findAll()
 * @method ImageCrop[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageCropRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageCrop::class);
    }

    // /**
    //  * @return ImageCrop[] Returns an array of ImageCrop objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ImageCrop
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
