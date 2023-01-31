<?php

namespace Base\Field;

use Base\Field\Type\ForexType;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

class ForexField extends SelectField implements FieldInterface
{
    public const OPTION_TARGET_FIELD_NAME = 'targetFieldName';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(ForexType::class)
            ->setTemplatePath('@EasyAdmin/crud/field/icon.html.twig')
            ->setTextAlign(TextAlign::CENTER);
    }
}
