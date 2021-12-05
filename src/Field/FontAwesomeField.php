<?php

namespace Base\Field;

use Base\Field\Type\FontAwesomeType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

final class FontAwesomeField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(FontAwesomeType::class)
            ->setTemplatePath('@EasyAdmin/crud/field/font_awesome.html.twig')
            ->setTextAlign(TextAlign::CENTER);
    }
}
