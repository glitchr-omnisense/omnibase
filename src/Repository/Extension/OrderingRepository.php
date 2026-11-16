<?php

namespace Base\Repository\Extension;

use Base\Entity\Extension\Ordering;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<object> (the entity its @method tags name is not declared in the core)
 *
 * @method Ordering|null find($id)
 * @method Ordering|null findOneBy(array $criteria, array $orderBy = null)
 * @method Ordering[]    findAll()
 * @method Ordering[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderingRepository extends ServiceEntityRepository
{
}
