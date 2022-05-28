<?php

namespace Base\Repository\Layout\Attribute\Common;

use Base\Entity\Layout\Attribute\Common\BaseAttribute;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method BaseAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method BaseAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method BaseAttribute[]    findAll()
 * @method BaseAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class BaseAttributeRepository extends ServiceEntityRepository
{

}
