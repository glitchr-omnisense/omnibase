<?php

namespace Base\Field;

use Symfony\Contracts\Translation\TranslatableInterface;

class DateTimeField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setKind(Kind::DATETIME)
            ->setFormTypeOption('widget', 'single_text')
            ->addCssClass('field-datetime')
            ->setDefaultColumns('col-md-4 col-xxl-3');
    }
}
