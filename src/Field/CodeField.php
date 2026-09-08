<?php

namespace Base\Field;

use Base\Field\Type\CodeType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A code of known length, one box per character (Base\Field\Type\CodeType).
 * Reads as plain text on index/detail.
 */
class CodeField implements FieldInterface
{
    use FieldTrait;

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(string $propertyName, $label = null)
    {
        return (new static())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(CodeType::class)
            ->addCssClass('field-code')
            ->setDefaultColumns('col-md-4 col-xxl-3');
    }

    public function setLength(int $length): static
    {
        return $this->setFormTypeOption('length', $length);
    }

    /** 'alnum' (default), 'digits' or 'alpha'. */
    public function setAlphabet(string $alphabet): static
    {
        return $this->setFormTypeOption('alphabet', $alphabet);
    }

    public function setUppercase(bool $uppercase = true): static
    {
        return $this->setFormTypeOption('uppercase', $uppercase);
    }
}
