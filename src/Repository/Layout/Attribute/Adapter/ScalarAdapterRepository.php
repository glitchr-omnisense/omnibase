<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\ScalarAdapter;

use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;
use Base\Repository\Layout\AttributeRepository;

/**
 * @method ScalarAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method ScalarAdapter|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method ScalarAdapter[]    findAll()
 * @method ScalarAdapter[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ScalarAdapterRepository extends AbstractAdapterRepository
{

}
