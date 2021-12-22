<?php

namespace Base\Entity\Sitemap\Attribute;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\Attribute;
use Base\Entity\Sitemap\AttributeInterface;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\ArrayAttributeRepository;
use Doctrine\DBAL\Types\ArrayType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @ORM\Entity(repositoryClass=ArrayAttributeRepository::class)
 * @DiscriminatorEntry( value = "array" )
 */

class ArrayAttribute extends Attribute implements IconizeInterface, AttributeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-clipboard-list"]; }

    public static function getType(): string { return TextType::class; }
    public static function getOptions(): array { return []; }
}
