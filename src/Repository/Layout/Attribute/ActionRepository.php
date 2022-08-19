<?php

namespace Base\Repository\Layout\Attribute;

use Base\Entity\Layout\Attribute\Action;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method Action|null find($id, $lockMode = null, $lockVersion = null)
 * @method Action|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Action[]    findAll()
 * @method Action[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ActionRepository extends AttributeRepository
{

}
