<?php

namespace Base\Field;

use Base\Field\Type\CurrencyType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

class CurrencyField extends SelectField implements FieldInterface
{
    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(CurrencyType::class)
            ->setTemplatePath('@Admin/crud/field/currency.html.twig');
    }
}
