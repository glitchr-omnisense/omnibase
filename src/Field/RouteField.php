<?php

namespace Base\Field;

use Base\Field\Type\RouteType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

class RouteField extends SelectField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(RouteType::class)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ICON_ONLY)
            ->setCustomOption(self::OPTION_SHOW_FIRST, self::SHOW_ALL)
            ->setCustomOption(self::OPTION_DISPLAY_LIMIT, 2)
            ->setTextAlign(TextAlign::RIGHT)
            ->setTemplatePath('@EasyAdmin/crud/field/select.html.twig');
    }
}
