<?php

namespace Base\Field;

use Symfony\Contracts\Translation\TranslatableInterface;

class CurrencyField extends SelectField implements FieldInterface
{
    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/currency')
            ->setKind(Kind::CURRENCY);
    }
}
