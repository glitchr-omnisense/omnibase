<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\HyperpatternAdapter;

use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;
use Base\Repository\Layout\AttributeRepository;

/**
 * @method HyperpatternAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method HyperpatternAdapter|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method HyperpatternAdapter[]    findAll()
 * @method HyperpatternAdapter[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class HyperpatternAdapterRepository extends AbstractAdapterRepository
{

}
