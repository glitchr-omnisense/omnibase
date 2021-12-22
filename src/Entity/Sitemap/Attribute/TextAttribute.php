<?php

namespace Base\Entity\Sitemap\Widget;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\Attribute;
use Base\Entity\Sitemap\AttributeInterface;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Widget\TextAttributeRepository;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @ORM\Entity(repositoryClass=TextAttributeRepository::class)
 * @DiscriminatorEntry( value = "text" )
 */

class TextAttribute extends Attribute implements IconizeInterface, AttributeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-paragraph"]; } 

    public static function getType(): string { return TextType::class; }
    public static function getOptions(): array { return []; }
}
