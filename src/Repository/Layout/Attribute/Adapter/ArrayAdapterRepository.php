<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\ArrayAdapter;

use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;
use Base\Repository\Layout\AttributeRepository;

/**
 * @method ArrayAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArrayAdapter|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method ArrayAdapter[]    findAll()
 * @method ArrayAdapter[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class ArrayAdapterRepository extends AbstractAdapterRepository
{
}
