<?php

namespace Base\Entity\Sitemap\Widget;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\Widget;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Widget\MenuRepository;

/**
 * @ORM\Entity(repositoryClass=MenuRepository::class)
 * @DiscriminatorEntry( value = "menu" )
 */

class Menu extends Widget
{
}