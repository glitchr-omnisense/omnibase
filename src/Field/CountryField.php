<?php

namespace Base\Field;

use Base\Field\Type\CountryType;
use Base\Field\Type\SelectType;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

final class CountryField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/country')
            ->setFormType(CountryType::class)
            ->setTemplatePath('@EasyAdmin/crud/field/country.html.twig')
            ->addCssClass('field-country');
    }
}
