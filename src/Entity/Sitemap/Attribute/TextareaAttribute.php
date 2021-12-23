<?php

namespace Base\Entity\Sitemap\Attribute;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\Attribute;
use Base\Entity\Sitemap\AttributeInterface;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\TextareaAttributeRepository;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @ORM\Entity(repositoryClass=TextareaAttributeRepository::class)
 * @DiscriminatorEntry( value = "textarea" )
 */

class TextareaAttribute extends AbstractAttribute implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-align-left"]; }

    public static function getType(): string { return TextareaType::class; }
    public static function getOptions(): array { return []; }
}
