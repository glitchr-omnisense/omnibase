<?php

namespace Base\Repository\Layout\Attribute\Scope;

use Base\Entity\Layout\Attribute\Scope\ThreadScope;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method ThreadScope|null find($id, $lockMode = null, $lockVersion = null)
 * @method ThreadScope|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method ThreadScope[]    findAll()
 * @method ThreadScope[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ThreadScopeRepository extends AttributeRepository
{

}
