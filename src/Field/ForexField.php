<?php

namespace Base\Field;

use Base\Field\Type\ForexType;
use Base\Field\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

class ForexField extends SelectField implements FieldInterface
{
    public const OPTION_TARGET_FIELD_NAME = 'targetFieldName';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/icon')
            ->setFormType(ForexType::class)
            ->setTextAlign(TextAlign::CENTER);
    }
}
