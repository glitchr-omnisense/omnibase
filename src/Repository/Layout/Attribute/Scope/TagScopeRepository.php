<?php

namespace Base\Repository\Layout\Attribute\Scope;

use Base\Entity\Layout\Attribute\Scope\TagScope;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method TagScope|null find($id, $lockMode = null, $lockVersion = null)
 * @method TagScope|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method TagScope[]    findAll()
 * @method TagScope[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class TagScopeRepository extends AttributeRepository
{

}
