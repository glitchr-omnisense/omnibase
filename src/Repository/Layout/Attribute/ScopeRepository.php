<?php

namespace Base\Repository\Layout\Attribute;

use Base\Entity\Layout\Attribute\Scope;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method Scope|null find($id, $lockMode = null, $lockVersion = null)
 * @method Scope|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Scope[]    findAll()
 * @method Scope[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ScopeRepository extends AttributeRepository
{

}
