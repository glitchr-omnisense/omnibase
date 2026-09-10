<?php

namespace Base\Field;

use Base\Enum\Gender;
use Base\Field\Type\GenderType;
use Symfony\Contracts\Translation\TranslatableInterface;

class GenderField extends SelectField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/select')
            ->setFormType(GenderType::class)
            // Both spellings on purpose - see StateField::new()'s own
            // comment: OPTION_CLASS is the form side, OPTION_ENUM_CLASS is
            // what the index/detail badge reads for icon, colour and label.
            ->setCustomOption(SelectField::OPTION_CLASS, Gender::class)
            ->setCustomOption(SelectField::OPTION_ENUM_CLASS, Gender::class);
    }
}
