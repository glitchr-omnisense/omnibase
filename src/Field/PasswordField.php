<?php

namespace Base\Field;

use Base\Field\Type\PasswordType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

/**
 *
 */
final class PasswordField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(PasswordType::class)
            ->addCssClass('field-password');
    }

    public function setRevealer(bool $revealer): self
    {
        $this->setFormTypeOption('revealer', $revealer);

        return $this;
    }

    public function secure(bool $secure = true): self
    {
        $this->setFormTypeOption('secure', $secure);

        return $this;
    }

    public function setRepeater(bool $repeater): self
    {
        $this->setFormTypeOption('repeater', $repeater);

        return $this;
    }

    public function showInline(bool $inline = true): self
    {
        $this->setFormTypeOption('inline', $inline);

        return $this;
    }
}
