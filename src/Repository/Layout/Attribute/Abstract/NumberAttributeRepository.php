<?php

namespace Base\Repository\Layout\Attribute\Abstract;

use Base\Entity\Layout\Attribute\Abstract\NumberAttribute;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method NumberAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method NumberAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method NumberAttribute[]    findAll()
 * @method NumberAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class NumberAttributeRepository extends AttributeRepository
{

}
