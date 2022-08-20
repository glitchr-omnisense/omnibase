<?php

namespace Base\Repository\Layout\Attribute\Abstract;

use Base\Entity\Layout\Attribute\Abstract\MoneyAttribute;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method MoneyAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method MoneyAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method MoneyAttribute[]    findAll()
 * @method MoneyAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class MoneyAttributeRepository extends AttributeRepository
{

}
