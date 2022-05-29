<?php

namespace Base\Repository\Layout\Attribute\Abstract;

use Base\Entity\Layout\Attribute\Abstract\ArrayAttribute;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method ArrayAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArrayAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method ArrayAttribute[]    findAll()
 * @method ArrayAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ArrayAttributeRepository extends AttributeRepository
{

}
