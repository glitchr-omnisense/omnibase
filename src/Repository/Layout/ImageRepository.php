<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\Image;
use Base\Database\Repository\ServiceEntityRepository;


/**
 * @method Image|null find($id)
 * @method Image|null findOneBy(array $criteria, array $orderBy = null)
 * @method Image[]    findAll()
 * @method Image[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageRepository extends ServiceEntityRepository
{}
