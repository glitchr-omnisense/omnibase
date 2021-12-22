<?php

namespace Base\Field;

use Base\Field\Type\DiscriminatorType;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

class DiscriminatorField extends SelectField implements FieldInterface
{
    use FieldTrait; 

    public static function new(string $propertyName = "id", ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label ?? "Type")
            ->setTemplateName('crud/field/text')
            ->setFormType(DiscriminatorType::class)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ALL)
            ->setCustomOption(self::OPTION_SHOW_FIRST, self::SHOW_ALL)
            ->setTextAlign(TextAlign::RIGHT)
            ->setColumns(6)
            ->setTemplatePath('@EasyAdmin/crud/field/select.html.twig');
    }
}
