<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use \Symfony\Component\Validator\Constraints\Length;

final class ImpersonateField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setTemplatePath("@EasyAdmin/crud/field/impersonate.html.twig")
            ->setFormType(TextType::class);
    }
}
