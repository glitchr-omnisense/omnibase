<?php

namespace Base\Field;

use Base\Field\Type\IconType;
use Base\Model\IconProvider\IconAdapterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

class IconField extends SelectField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_TARGET_FIELD_NAME = 'targetFieldName';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(IconType::class)
            ->setTemplatePath('@EasyAdmin/crud/field/icon.html.twig')
            ->setTextAlign(TextAlign::CENTER);
    }

    public function setTargetColor(string $fieldName)
    {
        $this->setCustomOption(self::OPTION_TARGET_FIELD_NAME, $fieldName);
        return $this;
    }

    public function setAdapter(IconAdapterInterface|string $objectOrClass)
    {
        $this->setFormTypeOption("adapter", is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass);
        return $this;
    }
}
