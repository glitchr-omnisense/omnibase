<?php

namespace Base\Repository\Layout\Attribute\Common;

use Base\Entity\Layout\Attribute\Common\RuleAttribute;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method RuleAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method RuleAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method RuleAttribute[]    findAll()
 * @method RuleAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class RuleAttributeRepository extends ServiceEntityRepository
{

}
