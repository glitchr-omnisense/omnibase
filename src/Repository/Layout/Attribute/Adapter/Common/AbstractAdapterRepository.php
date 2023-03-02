<?php

namespace Base\Repository\Layout\Attribute\Adapter\Common;

use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method AbstractAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractAdapter|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method AbstractAdapter[]    findAll()
 * @method AbstractAdapter[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class AbstractAdapterRepository extends ServiceEntityRepository
{
}
