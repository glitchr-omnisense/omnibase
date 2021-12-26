<?php

namespace Base\Repository\Sitemap\Attribute\Abstract;

use Base\Entity\Sitemap\Attribute\Abstract\DateTimeAttribute;

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