<?php

namespace Base\Repository\Layout\Attribute\Abstract;

use Base\Entity\Layout\Attribute\Abstract\ScalarAttribute;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method ScalarAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method ScalarAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method ScalarAttribute[]    findAll()
 * @method ScalarAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ScalarAttributeRepository extends AttributeRepository
{

}
