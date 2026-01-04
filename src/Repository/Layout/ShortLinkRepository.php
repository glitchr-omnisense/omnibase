<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\ShortLink;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method ShortLink|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShortLink|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method ShortLink[]    findAll()
 * @method ShortLink[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class ShortLinkRepository extends ServiceEntityRepository
{
}
