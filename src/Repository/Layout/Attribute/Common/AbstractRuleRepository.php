<?php

namespace Base\Repository\Layout\Attribute\Common;

use Base\Entity\Layout\Attribute\Common\AbstractRule;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method AbstractRule|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractRule|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method AbstractRule[]    findAll()
 * @method AbstractRule[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class AbstractRuleRepository extends ServiceEntityRepository
{

}
