<?php

namespace Base\Repository\Layout\Attribute\Abstract;

use Base\Entity\Layout\Attribute\Abstract\CountryAttribute;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method CountryAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method CountryAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method CountryAttribute[]    findAll()
 * @method CountryAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class CountryAttributeRepository extends AttributeRepository
{

}
