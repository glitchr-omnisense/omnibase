<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Base\Field\Type\SlugType;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class SlugField implements FieldInterface
{
    use FieldTrait;

    public const OPTION_LEADING_HASH = 'leadingHash';
    public const OPTION_TARGET_FIELD_NAME = 'targetFieldName';
    public const OPTION_UNLOCK_CONFIRMATION_MESSAGE = 'unlockConfirmationMessage';

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setTemplatePath('@EasyAdmin/crud/field/slug.html.twig')
            ->setFormType(SlugType::class)
            ->showLeadingHash()
            ->setCustomOption(self::OPTION_TARGET_FIELD_NAME, null)
            ->setCustomOption(self::OPTION_UNLOCK_CONFIRMATION_MESSAGE, null)
            ->addCssClass('field-text')
        ;
    }

    public function setTargetFieldName(string $fieldName): self
    {
        $this->setCustomOption(self::OPTION_TARGET_FIELD_NAME, $fieldName);
        return $this;
    }

    public function setSeparator(string $separator): self
    {
        $this->setFormTypeOption("separator", $separator);
        return $this;
    }

    public function keep(string $keep): self
    {
        $this->setFormTypeOption("keep", $keep);
        return $this;
    }

    public function uppercase(bool $upper = true): self
    {
        $this->setFormTypeOption("upper", $upper);
        return $this;
    }

    public function setUnlockConfirmationMessage(string $message): self
    {
        $this->setCustomOption(self::OPTION_UNLOCK_CONFIRMATION_MESSAGE, $message);

        return $this;
    }

    public function showLeadingHash(bool $show = true): self
    {
        $this->setCustomOption(self::OPTION_LEADING_HASH, $show);
        return $this;
    }
}
