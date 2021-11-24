<?php

namespace Base\Entity\Sitemap\Widget;

use Doctrine\ORM\Mapping as ORM;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\Widget;

/**
 * @ORM\Entity(repositoryClass=MenuRepository::class)
 * @DiscriminatorEntry( value = "menu" )
 */

class Menu extends Widget { }