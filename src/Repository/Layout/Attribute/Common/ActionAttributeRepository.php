<?php

namespace Base\Repository\Layout\Attribute\Common;

use Base\Entity\Layout\Attribute\Common\ActionAttribute;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method ActionAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActionAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method ActionAttribute[]    findAll()
 * @method ActionAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ActionAttributeRepository extends ServiceEntityRepository
{

}
