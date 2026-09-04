<?php

namespace Base\Repository\User\Attribute;

use Base\Entity\User\Attribute\GroupRule;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method GroupRule|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupRule|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupRule[]    findAll()
 * @method GroupRule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupRuleRepository extends ServiceEntityRepository
{
}
