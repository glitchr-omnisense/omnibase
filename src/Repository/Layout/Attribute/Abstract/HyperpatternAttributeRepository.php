<?php

namespace Base\Repository\Layout\Attribute\Abstract;

use Base\Entity\Layout\Attribute\Abstract\HyperpatternAttribute;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method HyperpatternAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method HyperpatternAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method HyperpatternAttribute[]    findAll()
 * @method HyperpatternAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class HyperpatternAttributeRepository extends AttributeRepository
{

}
