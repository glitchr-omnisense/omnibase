<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\Attribute;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Attribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method Attribute|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Attribute[]    findAll()
 * @method Attribute[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class AttributeRepository extends ServiceEntityRepository
{
}
