<?php

namespace Base\Repository\Layout\Attribute;

use Base\Entity\Layout\Attribute\Hyperlink;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method Hyperlink|null find($id, $lockMode = null, $lockVersion = null)
 * @method Hyperlink|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Hyperlink[]    findAll()
 * @method Hyperlink[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class HyperlinkRepository extends AttributeRepository
{
}
