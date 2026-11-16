<?php

namespace Base\Repository\Thread;

use Base\Entity\Thread\Tag;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Tag>
 *
 * @method Tag|null find($id)
 * @method Tag|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class TaxonRepository extends ServiceEntityRepository
{
}
