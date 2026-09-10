<?php

namespace Base\Field;

use Base\Enum\ThreadState;
use Base\Field\Type\StateType;
use Base\Field\Option\TextAlign;
use Symfony\Contracts\Translation\TranslatableInterface;

class StateField extends SelectField
{
    public const OPTION_CLASS = 'class';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/select')
            ->setFormType(StateType::class)
            ->setCustomOption(self::OPTION_CLASS, ThreadState::class)
            // The badge template (crud/field/select) resolves a value's
            // icon, colour and translated label from OPTION_ENUM_CLASS -
            // OPTION_CLASS is the FORM side's option and is read by nothing
            // on the render side. Declaring only the latter is why every
            // StateField cell rendered as a bare, icon-less, uncoloured
            // "State draft" (the raw constant humanized) while a plain
            // SelectField::new('state')->setEnumClass(ThreadState::class)
            // - which is what every other CRUD in the app writes by hand -
            // rendered the real badge. Same class, both options, so the two
            // spellings finally agree.
            ->setCustomOption(self::OPTION_ENUM_CLASS, ThreadState::class)
            ->setCustomOption(self::OPTION_SHOW, self::SHOW_ALL)
            ->setTextAlign(TextAlign::LEFT)
            ->setFormTypeOption('capitalize', false);
    }
}
