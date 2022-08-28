<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\AbstractAdapter;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method AbstractAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractAdapter|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method AbstractAdapter[]    findAll()
 * @method AbstractAdapter[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

abstract class AbstractAdapterRepository extends ServiceEntityRepository
{

}
