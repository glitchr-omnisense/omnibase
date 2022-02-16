<?php

namespace Base\Repository\Extension;

use Base\Entity\Extension\Sort;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Sort|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sort|null findOneBy(array $criteria, array $orderBy = null)
 * @method Sort[]    findAll()
 * @method Sort[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SortRepository extends ServiceEntityRepository
{

}
