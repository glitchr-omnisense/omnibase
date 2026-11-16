<?php

namespace Base\Repository\Layout\Attribute\Common;

use Base\Entity\Layout\Attribute\Common\AbstractAction;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<AbstractAction>
 *
 * @method AbstractAction|null find($id)
 * @method AbstractAction|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method AbstractAction[]    findAll()
 * @method AbstractAction[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class AbstractActionRepository extends ServiceEntityRepository
{
}
