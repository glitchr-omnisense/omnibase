<?php

namespace Base\Entity\Sitemap\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\Attribute;
use Base\Entity\Sitemap\AttributeInterface;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\Abstract\TextareaAttributeRepository;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @ORM\Entity(repositoryClass=TextareaAttributeRepository::class)
 * @DiscriminatorEntry( value = "textarea" )
 */

class TextareaAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __staticIconize() : ?array { return ["fas fa-align-left"]; }

    public static function getType(): string { return TextareaType::class; }
    public function getOptions(): array { return []; }
}
