<?php

namespace Base\Repository\Layout\Attribute\Abstract;

use Base\Entity\Layout\Attribute\Abstract\TextAttribute;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method TextAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method TextAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method TextAttribute[]    findAll()
 * @method TextAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class TextAttributeRepository extends AttributeRepository
{

}
