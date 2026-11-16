<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\ShortLink;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<ShortLink>
 *
 * @method ShortLink|null find($id)
 * @method ShortLink|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method ShortLink[]    findAll()
 * @method ShortLink[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class ShortLinkRepository extends ServiceEntityRepository
{
}
