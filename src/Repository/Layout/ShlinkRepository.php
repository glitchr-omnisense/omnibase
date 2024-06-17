<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\Shlink;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Shlink|null find($id, $lockMode = null, $lockVersion = null)
 * @method Shlink|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Shlink[]    findAll()
 * @method Shlink[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class ShlinkRepository extends ServiceEntityRepository
{
}
