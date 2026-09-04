<?php

namespace Base\Repository\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\ScoreAdapter;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method ScoreAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method ScoreAdapter|null findOneBy(array $criteria, array $orderBy = null)
 * @method ScoreAdapter[]    findAll()
 * @method ScoreAdapter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ScoreAdapterRepository extends ServiceEntityRepository
{
}
