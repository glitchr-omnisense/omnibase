<?php

namespace Base\Field;

use Symfony\Contracts\Translation\TranslatableInterface;

class DateField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/date')
            ->setKind(Kind::DATE)
            ->setFormTypeOption('widget', 'single_text')
            ->addCssClass('field-date')
            ->setDefaultColumns('col-md-4 col-xxl-3');
    }
}
