<?php

namespace Base\Field;

use Base\Field\Traits\SelectFieldInterface;
use Base\Field\Traits\SelectFieldTrait;
use Base\Field\Type\GenderType;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use \Symfony\Component\Validator\Constraints\Length;

final class GenderField implements FieldInterface, SelectFieldInterface
{
    use FieldTrait;
    use SelectFieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(GenderType::class)
            ->setTemplatePath('@EasyAdmin/crud/field/select.html.twig');
    }
}
