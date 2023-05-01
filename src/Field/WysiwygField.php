<?php

namespace Base\Field;

use Base\Field\Type\WysiwygType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

/**
 *
 */
final class WysiwygField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_SHORTEN_LENGTH = 'shortenStrLength';
    public const OPTION_SHORTEN_POSITION = 'shortenStrPosition';
    public const OPTION_SHORTEN_SEPARATOR = 'shortenStrSeparator';

    public const OPTION_STRIP_TAGS = 'stripTags';
    public const OPTION_RENDER_AS_BOOLEAN = 'renderAsBoolean';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/textarea')
            ->shorten()
            ->setFormType(WysiwygType::class)
            ->setCustomOption(self::OPTION_STRIP_TAGS, true);
    }

    /**
     * @param bool $asBool
     * @return $this
     */
    /**
     * @param bool $asBool
     * @return $this
     */
    public function renderAsBoolean(bool $asBool = true)
    {
        $this->setCustomOption(self::OPTION_RENDER_AS_BOOLEAN, $asBool);

        return $this;
    }

    public function shorten(int $length = 100, int $position = SHORTEN_BACK, string $separator = ' [..] '): self
    {
        $this->setCustomOption(self::OPTION_SHORTEN_LENGTH, $length);
        $this->setCustomOption(self::OPTION_SHORTEN_POSITION, $position);
        $this->setCustomOption(self::OPTION_SHORTEN_SEPARATOR, $separator);

        return $this;
    }

    /**
     * @param bool $stripTags
     * @return $this
     */
    /**
     * @param bool $stripTags
     * @return $this
     */
    public function stripTags(bool $stripTags = true)
    {
        $this->setCustomOption(self::OPTION_STRIP_TAGS, $stripTags);

        return $this;
    }
}
