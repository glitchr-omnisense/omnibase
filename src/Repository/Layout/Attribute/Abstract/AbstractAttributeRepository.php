<?php

namespace Base\Repository\Layout\Attribute\Abstract;

use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method AbstractAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method AbstractAttribute[]    findAll()
 * @method AbstractAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class AbstractAttributeRepository extends ServiceEntityRepository
{

}
