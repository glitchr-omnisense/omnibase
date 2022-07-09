<?php

namespace Base\Field;

use App\Enum\UserRole;
use Base\Field\Type\RoleType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;

class RoleField extends SelectField
{
    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(RoleType::class)
            ->setCustomOption(SelectField::OPTION_CLASS, UserRole::class)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ICON_ONLY)
            ->setCustomOption(self::OPTION_SHOW_FIRST, self::SHOW_ALL)
            ->setCustomOption(self::OPTION_DISPLAY_LIMIT, 2)
            ->setTextAlign(TextAlign::RIGHT)
            ->setTemplatePath('@EasyAdmin/crud/field/select.html.twig');
    }
}
