<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

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

    public function setRevealer(string $revealer): self
    {
        $this->setFormTypeOption("revealer", $revealer);
        return $this;
    }
    public function setRepeater(string $repeater): self
    {
        $this->setFormTypeOption("repeater", $repeater);
        return $this;
    }
}
