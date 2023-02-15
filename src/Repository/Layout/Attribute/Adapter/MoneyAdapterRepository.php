<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\MoneyAdapter;

use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;
use Base\Repository\Layout\AttributeRepository;

/**
 * @method MoneyAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method MoneyAdapter|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method MoneyAdapter[]    findAll()
 * @method MoneyAdapter[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class MoneyAdapterRepository extends AbstractAdapterRepository
{

}
