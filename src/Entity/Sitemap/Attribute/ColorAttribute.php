<?php

namespace Base\Entity\Sitemap\Attribute;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\Attribute;
use Base\Entity\Sitemap\AttributeInterface;
use Base\Model\IconizeInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\ArrayAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=ColorAttributeRepository::class)
 * @DiscriminatorEntry( value = "color" )
 */

class ColorAttribute extends Attribute implements IconizeInterface, AttributeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-tint"]; }

    public static function getType(): string { return TextType::class; }
    public static function getOptions(): array { return []; }
}
