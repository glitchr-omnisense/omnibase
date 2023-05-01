<?php

namespace Base\Field;

use Base\Field\Type\IconType;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

/**
 *
 */
class IconField extends SelectField implements FieldInterface
{
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

    /**
     * @param string $fieldName
     * @return $this
     */
    /**
     * @param string $fieldName
     * @return $this
     */
    public function setTargetColor(string $fieldName)
    {
        $this->setCustomOption(self::OPTION_TARGET_FIELD_NAME, $fieldName);
        return $this;
    }

    /**
     * @param IconAdapterInterface|string $objectOrClass
     * @return $this
     */
    /**
     * @param IconAdapterInterface|string $objectOrClass
     * @return $this
     */
    public function setAdapter(IconAdapterInterface|string $objectOrClass)
    {
        $this->setFormTypeOption("adapter", is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass);
        return $this;
    }
}
