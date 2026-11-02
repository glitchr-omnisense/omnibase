<?php

namespace Base\Field;

use Base\Field\Choice\Countries;
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
            ->setKind(Kind::COUNTRY)
            ->setChoices(Countries::getChoices())
            ->addCssClass('field-country');
    }
}
