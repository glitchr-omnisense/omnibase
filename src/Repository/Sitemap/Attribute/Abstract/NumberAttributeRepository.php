<?php

namespace Base\Repository\Sitemap\Attribute\Abstract;

use Base\Entity\Sitemap\Attribute\Abstract\NumberAttribute;

use Base\Repository\Sitemap\AttributeRepository;

/**
 * @method NumberAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method NumberAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method NumberAttribute[]    findAll()
 * @method NumberAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class NumberAttributeRepository extends AttributeRepository
{

}
