<?php

namespace Base\Repository\Layout\Attribute\Adapter\Common;

use Base\Entity\Layout\Attribute\Adapter\Common\AbstractScopeAdapter;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method AbstractScopeAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractScopeAdapter|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method AbstractScopeAdapter[]    findAll()
 * @method AbstractScopeAdapter[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class AbstractScopeAdapterRepository extends AbstractAdapterRepository
{

}
