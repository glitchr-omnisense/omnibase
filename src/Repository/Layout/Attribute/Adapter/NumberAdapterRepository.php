<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\NumberAdapter;

use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;

/**
 * @method NumberAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method NumberAdapter|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method NumberAdapter[]    findAll()
 * @method NumberAdapter[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class NumberAdapterRepository extends AbstractAdapterRepository
{
}
