<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\Semantic;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Semantic|null find($id, $lockMode = null, $lockVersion = null)
 * @method Semantic|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Semantic[]    findAll()
 * @method Semantic[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class SemanticRepository extends ServiceEntityRepository
{
}
