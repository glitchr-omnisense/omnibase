<?php

namespace Base\Repository\User\Attribute\Action;

use Base\Entity\User\Attribute\Action\GrantPermissionAdapter;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method GrantPermissionAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method GrantPermissionAdapter|null findOneBy(array $criteria, array $orderBy = null)
 * @method GrantPermissionAdapter[]    findAll()
 * @method GrantPermissionAdapter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GrantPermissionAdapterRepository extends ServiceEntityRepository
{
}
