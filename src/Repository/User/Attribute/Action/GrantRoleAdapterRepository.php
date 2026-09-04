<?php

namespace Base\Repository\User\Attribute\Action;

use Base\Entity\User\Attribute\Action\GrantRoleAdapter;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method GrantRoleAdapter|null find($id, $lockMode = null, $lockVersion = null)
 * @method GrantRoleAdapter|null findOneBy(array $criteria, array $orderBy = null)
 * @method GrantRoleAdapter[]    findAll()
 * @method GrantRoleAdapter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GrantRoleAdapterRepository extends ServiceEntityRepository
{
}
