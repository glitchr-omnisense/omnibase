<?php

namespace Base\Repository\Layout\Attribute\Adapter\Common;

use Base\Entity\Layout\Attribute\Adapter\Common\AbstractRuleAdapter;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method AbstractRuleAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractRuleAdapter|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method AbstractRuleAdapter[]    findAll()
 * @method AbstractRuleAdapter[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class AbstractRuleAdapterRepository extends AbstractAdapterRepository
{
}
