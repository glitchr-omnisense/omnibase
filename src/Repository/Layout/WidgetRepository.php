<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\Widget;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Widget|null find($id, $lockMode = null, $lockVersion = null)
 * @method Widget|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Widget[]    findAll()
 * @method Widget[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class WidgetRepository extends ServiceEntityRepository
{
}
