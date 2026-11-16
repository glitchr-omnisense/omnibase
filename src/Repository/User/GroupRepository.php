<?php

namespace Base\Repository\User;

use App\Entity\User\Group;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Group>
 *
 * @method Group|null find($id)
 * @method Group|null findOneBy(array $criteria, array $orderBy = null)
 * @method Group[]    findAll()
 * @method Group[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupRepository extends ServiceEntityRepository
{
}
