<?php

namespace Base\Repository\Sitemap\Attribute\Abstract;

use Base\Entity\Sitemap\Attribute\Abstract\TextAttribute;

use Base\Repository\Sitemap\AttributeRepository;

/**
 * @method TextAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method TextAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method TextAttribute[]    findAll()
 * @method TextAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class TextAttributeRepository extends AttributeRepository
{

}
