<?php

namespace Base\Field;

use Base\Enum\Gender;
use Base\Field\Type\GenderType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

class GenderField extends SelectField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(GenderType::class)
            ->setCustomOption(SelectField::OPTION_CLASS, Gender::class)
            ->setTemplatePath('@Admin/crud/field/select.html.twig');
    }
}
