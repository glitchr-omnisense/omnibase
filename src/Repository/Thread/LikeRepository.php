<?php

namespace Base\Repository\Thread;

use App\Entity\Thread\Like;
use Base\Entity\Thread;
use Doctrine\Persistence\ManagerRegistry;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Like|null find($id, $lockMode = null, $lockVersion = null)
 * @method Like|null findOneBy(array $criteria, array $orderBy = null)
 * @method Like[]    findAll()
 * @method Like[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LikeRepository extends ServiceEntityRepository
{
}
