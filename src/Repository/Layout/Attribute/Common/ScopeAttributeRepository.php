<?php

namespace Base\Repository\Layout\Attribute\Common;

use Base\Entity\Layout\Attribute\Common\ScopeAttribute;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method ScopeAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method ScopeAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method ScopeAttribute[]    findAll()
 * @method ScopeAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ScopeAttributeRepository extends ServiceEntityRepository
{

}
