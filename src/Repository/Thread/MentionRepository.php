<?php

namespace Base\Repository\Thread;

use App\Entity\Thread\Mention;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Mention|null find($id, $lockMode = null, $lockVersion = null)
 * @method Mention|null findOneBy(array $criteria, array $orderBy = null)
 * @method Mention[]    findAll()
 * @method Mention[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MentionRepository extends ServiceEntityRepository
{
}
