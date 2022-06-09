<?php

namespace Base\Field;

use Base\Field\Type\QuillType;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

final class QuillField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_SHORTEN_LENGTH    = 'shortenStrLength';
    public const OPTION_SHORTEN_POSITION  = 'shortenStrPosition';
    public const OPTION_SHORTEN_SEPARATOR = 'shortenStrSeparator';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/textarea')
            ->shorten()
            ->setFormType(QuillType::class);
    }

    public function shorten(int $length = 100, int $position = SHORTEN_BACK, string $separator = " [..] "): self
    {
        $this->setCustomOption(self::OPTION_SHORTEN_LENGTH, $length);
        $this->setCustomOption(self::OPTION_SHORTEN_POSITION, $position);
        $this->setCustomOption(self::OPTION_SHORTEN_SEPARATOR, $separator);

        return $this;
    }
}
