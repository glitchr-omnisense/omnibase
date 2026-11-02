<?php

namespace Base\Field;

use Base\Field\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

final class ColorPickerField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setTextAlign(TextAlign::CENTER)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/color')
            ->setKind(Kind::COLOR_PICKER);
    }
}
