<?php

namespace Base\Repository\User;

use Base\Entity\User\Address;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Address>
 *
 * @method Address|null find($id)
 * @method Address|null findOneBy(array $criteria, array $orderBy = null)
 * @method Address[]    findAll()
 * @method Address[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */

class AddressRepository extends ServiceEntityRepository
{
}
