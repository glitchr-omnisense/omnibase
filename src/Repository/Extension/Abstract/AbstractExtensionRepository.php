<?php

namespace Base\Repository\Extension\Abstract;

use Base\Entity\Extension\Abstract\AbstractExtension;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method AbstractExtension|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractExtension|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbstractExtension[]    findAll()
 * @method AbstractExtension[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AbstractExtensionRepository extends ServiceEntityRepository
{

}
