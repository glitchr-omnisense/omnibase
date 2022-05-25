<?php

namespace Base\Repository\Extension;

use Base\Entity\Extension\TrashBall;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method TrashBall|null find($id, $lockMode = null, $lockVersion = null)
 * @method TrashBall|null findOneBy(array $criteria, array $orderBy = null)
 * @method TrashBall[]    findAll()
 * @method TrashBall[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrashBallRepository extends ServiceEntityRepository
{

}
