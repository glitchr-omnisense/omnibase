<?php

namespace Base\Field;

use Base\Enum\Gender;
use Base\Field\Type\GenderType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

class GenderField extends SelectField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(GenderType::class)
            ->setCustomOption(SelectField::OPTION_CLASS, Gender::class)
            ->setTemplatePath('@EasyAdmin/crud/field/select.html.twig');
    }
}
