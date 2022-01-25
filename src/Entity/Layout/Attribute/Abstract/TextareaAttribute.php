<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute;
use Base\Entity\Layout\AttributeInterface;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Abstract\TextareaAttributeRepository;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @ORM\Entity(repositoryClass=TextareaAttributeRepository::class)
 * @DiscriminatorEntry( value = "textarea" )
 */

class TextareaAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-align-left"]; }

    public static function getType(): string { return TextareaType::class; }
    public function getOptions(): array { return []; }
}
