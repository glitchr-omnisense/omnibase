<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\TextAdapter;

use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;
use Base\Repository\Layout\AttributeRepository;

/**
 * @method TextAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method TextAdapter|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method TextAdapter[]    findAll()
 * @method TextAdapter[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class TextAdapterRepository extends AbstractAdapterRepository
{
}
