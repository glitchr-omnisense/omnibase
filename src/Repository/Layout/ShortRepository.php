<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\Short;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Short|null find($id, $lockMode = null, $lockVersion = null)
 * @method Short|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Short[]    findAll()
 * @method Short[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */

class ShortRepository extends ServiceEntityRepository
{

}
