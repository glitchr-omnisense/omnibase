<?php

namespace Base\Repository\Sitemap\Attribute;

use Base\Entity\Sitemap\Attribute\DateTimeAttribute;

use Base\Repository\Sitemap\AttributeRepository;

/**
 * @method DateTimeAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method DateTimeAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method DateTimeAttribute[]    findAll()
 * @method DateTimeAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class DateTimeAttributeRepository extends AttributeRepository
{
    
}