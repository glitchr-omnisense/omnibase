<?php

namespace Base\Field;

use Base\Field\Traits\SelectFieldInterface;
use Base\Field\Traits\SelectFieldTrait;
use Base\Field\Type\SelectType;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use Symfony\Component\Config\Definition\Exception\Exception;

#
# This class has been redefined because of an non-fixed issue with multiple option
#
final class SelectField implements FieldInterface, SelectFieldInterface
{
    use FieldTrait;
    use SelectFieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(SelectType::class)
            ->setTemplatePath('@EasyAdmin/crud/field/select.html.twig')
            ->addCssClass('field-select');
    }
}