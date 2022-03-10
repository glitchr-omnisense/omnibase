<?php

namespace Base\Entity\Extension;

use Base\Database\Traits\EntityExtensionTrait;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Extension\OrderingRepository;

/**
 * @ORM\Entity(repositoryClass=OrderingRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class Ordering implements IconizeInterface
{
    use EntityExtensionTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-sort-alpha-down"]; } 
}
