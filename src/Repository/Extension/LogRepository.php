<?php

namespace Base\Repository\Extension;

use Base\Entity\Extension\Log;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<object> (the entity its @method tags name is not declared in the core)
 *
 * @method Log|null find($id)
 * @method Log|null findOneBy(array $criteria, array $orderBy = null)
 * @method Log[]    findAll()
 * @method Log[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LogRepository extends ServiceEntityRepository
{
}
