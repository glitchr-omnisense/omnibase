<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\CountryAdapter;

use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;
use Base\Repository\Layout\AttributeRepository;

/**
 * @method CountryAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method CountryAdapter|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method CountryAdapter[]    findAll()
 * @method CountryAdapter[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class CountryAdapterRepository extends AbstractAdapterRepository
{
}
