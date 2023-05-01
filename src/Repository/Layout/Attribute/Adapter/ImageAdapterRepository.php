<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\ImageAdapter;

use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;

/**
 * @method ImageAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageAdapter|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method ImageAdapter[]    findAll()
 * @method ImageAdapter[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class ImageAdapterRepository extends AbstractAdapterRepository
{
}
