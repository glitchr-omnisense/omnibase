<?php

namespace Base\Repository\Layout\Attribute\Abstract;

use Base\Entity\Layout\Attribute\Abstract\DateTimeAttribute;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method DateTimeAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method DateTimeAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method DateTimeAttribute[]    findAll()
 * @method DateTimeAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class DateTimeAttributeRepository extends AttributeRepository
{
    
}