<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Base\Field\Type\AttributeType;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class AttributeField extends SelectField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(AttributeType::class)
            ->addCssClass('field-text');
    }
}
