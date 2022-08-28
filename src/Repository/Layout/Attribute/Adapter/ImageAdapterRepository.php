<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\ImageAdapter;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method ImageAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageAdapter|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method ImageAdapter[]    findAll()
 * @method ImageAdapter[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ImageAdapterRepository extends AttributeRepository
{

}
