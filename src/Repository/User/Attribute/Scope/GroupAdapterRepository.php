<?php

namespace Base\Repository\User\Attribute\Scope;

use Base\Entity\User\Attribute\Scope\GroupAdapter;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method GroupAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupAdapter|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupAdapter[]    findAll()
 * @method GroupAdapter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupAdapterRepository extends ServiceEntityRepository
{
}
