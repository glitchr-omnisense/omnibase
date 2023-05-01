<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\DateTimeAdapter;

use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;

/**
 * @method DateTimeAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method DateTimeAdapter|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method DateTimeAdapter[]    findAll()
 * @method DateTimeAdapter[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class DateTimeAdapterRepository extends AbstractAdapterRepository
{
}
