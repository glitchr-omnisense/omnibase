<?php

namespace Base\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use Base\Field\Type\EmojiPickerType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;

final class EmojiPickerField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setTextAlign(TextAlign::CENTER)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setTemplatePath('@EasyAdmin/crud/field/emoji.html.twig')
            ->setFormType(EmojiPickerType::class);
    }
}
