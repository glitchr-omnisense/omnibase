<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\ColorAdapter;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method ColorAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method ColorAdapter|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method ColorAdapter[]    findAll()
 * @method ColorAdapter[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ColorAdapterRepository extends AttributeRepository
{

}
