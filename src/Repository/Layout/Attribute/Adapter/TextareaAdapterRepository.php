<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\TextareaAdapter;

use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;
use Base\Repository\Layout\AttributeRepository;

/**
 * @method TextareaAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method TextareaAdapter|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method TextareaAdapter[]    findAll()
 * @method TextareaAdapter[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class TextareaAdapterRepository extends AbstractAdapterRepository
{

}
