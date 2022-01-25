<?php

namespace Base\Entity\Layout\Widget;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Widget;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\MenuRepository;

/**
 * @ORM\Entity(repositoryClass=MenuRepository::class)
 * @DiscriminatorEntry( value = "menu" )
 */

class Menu extends Slot implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-compass"]; } 

    public function __toString() { return $this->getTitle(); }
}