<?php

namespace Base\Repository\Layout\Attribute;

use Base\Entity\Layout\Attribute\Rule;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method Rule|null find($id, $lockMode = null, $lockVersion = null)
 * @method Rule|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Rule[]    findAll()
 * @method Rule[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class RuleRepository extends AttributeRepository
{

}
