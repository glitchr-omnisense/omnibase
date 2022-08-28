<?php

namespace Base\Repository\Layout\Attribute\Scope;

use Base\Entity\Layout\Attribute\Scope\UserScope;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method UserScope|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserScope|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method UserScope[]    findAll()
 * @method UserScope[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class UserScopeRepository extends AttributeRepository
{

}
