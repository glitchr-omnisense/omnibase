<?php

namespace Base\Field;

use Base\Field\Type\RoleType;
use Base\Field\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

class RoleField extends SelectField
{
    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/select')
            ->setFormType(RoleType::class)
            ->setCustomOption(SelectField::OPTION_CLASS, class_exists('App\\Enum\\UserRole') ? 'App\\Enum\\UserRole' : 'Base\\Enum\\UserRole')
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ICON_ONLY)
            ->setCustomOption(self::OPTION_SHOW_FIRST, self::SHOW_ALL)
            ->setCustomOption(self::OPTION_DISPLAY_LIMIT, 2)
            ->setTextAlign(TextAlign::RIGHT);
    }
}
