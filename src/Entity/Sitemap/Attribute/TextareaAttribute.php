<?php

namespace Base\Entity\Sitemap\Widget;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\Attribute;
use Base\Entity\Sitemap\AttributeInterface;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Widget\TextareaAttributeRepository;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @ORM\Entity(repositoryClass=TextareaAttributeRepository::class)
 * @DiscriminatorEntry( value = "textarea" )
 */

class TextareaAttribute extends Attribute implements IconizeInterface, AttributeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-align-left"]; }

    public static function getType(): string { return TextareaType::class; }
    public static function getOptions(): array { return []; }
}
