<?php

namespace Base\Repository\Sitemap\Attribute\Abstract;

use Base\Entity\Sitemap\Attribute\Abstract\ArrayAttribute;

use Base\Repository\Sitemap\AttributeRepository;

/**
 * @method ArrayAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArrayAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method ArrayAttribute[]    findAll()
 * @method ArrayAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ArrayAttributeRepository extends AttributeRepository
{

}
