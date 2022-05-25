<?php

namespace Base\Repository\Layout\Attribute\Abstract;

use Base\Entity\Layout\Attribute\Abstract\ImageAttribute;

use Base\Repository\Layout\AttributeRepository;

/**
 * @method ImageAttribute|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImageAttribute|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method ImageAttribute[]    findAll()
 * @method ImageAttribute[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ImageAttributeRepository extends AttributeRepository
{

}
