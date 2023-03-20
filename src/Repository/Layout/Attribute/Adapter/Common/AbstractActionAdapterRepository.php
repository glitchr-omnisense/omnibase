<?php

namespace Base\Repository\Layout\Attribute\Adapter\Common;

use Base\Entity\Layout\Attribute\Adapter\Common\AbstractActionAdapter;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method AbstractActionAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractActionAdapter|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method AbstractActionAdapter[]    findAll()
 * @method AbstractActionAdapter[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class AbstractActionAdapterRepository extends AbstractAdapterRepository
{
}
