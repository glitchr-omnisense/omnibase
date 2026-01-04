<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\Tooltip;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Tooltip|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tooltip|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Tooltip[]    findAll()
 * @method Tooltip[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class TooltipRepository extends ServiceEntityRepository
{
}
