<?php

namespace Base\Repository\User;

use App\Entity\User\Permission;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Permission>
 *
 * @method Permission|null find($id)
 * @method Permission|null findOneBy(array $criteria, array $orderBy = null)
 * @method Permission[]    findAll()
 * @method Permission[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PermissionRepository extends ServiceEntityRepository
{
}
