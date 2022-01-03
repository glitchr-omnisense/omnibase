<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Base\Field\Type\PatternType;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class PatternField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_PATTERN_FIELD_NAME = 'pattern';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(PatternType::class)
            ->addCssClass('field-text')
        ;
    }

    public function setPatternFieldName(string $fieldName): self
    {
        $this->setFormType(self::OPTION_PATTERN_FIELD_NAME, $fieldName);
        return $this;
    }
}
