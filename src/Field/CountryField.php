<?php

namespace Base\Field;

use Base\Field\Type\CountryType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

class CountryField extends SelectField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/country')
            ->setFormType(CountryType::class)
            ->setChoices(CountryType::getChoices())
            ->setTemplatePath('@EasyAdmin/crud/field/country.html.twig')
            ->addCssClass('field-country');
    }
}
