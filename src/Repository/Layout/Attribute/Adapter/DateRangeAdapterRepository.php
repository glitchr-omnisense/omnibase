<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\DateRangeAdapter;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method DateRangeAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method DateRangeAdapter|null findOneBy(array $criteria, array $orderBy = null)
 * @method DateRangeAdapter[]    findAll()
 * @method DateRangeAdapter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DateRangeAdapterRepository extends ServiceEntityRepository
{
}
