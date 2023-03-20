<?php

namespace Base\Repository\Layout\Widget;

use Base\Entity\Layout\Widget\Page;

use Base\Repository\Layout\WidgetRepository;

/**
 * @method Page|null find($id, $lockMode = null, $lockVersion = null)
 * @method Page|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Page[]    findAll()
 * @method Page[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class PageRepository extends WidgetRepository
{
}
