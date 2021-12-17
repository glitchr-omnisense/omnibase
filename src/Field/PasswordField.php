<?php

namespace Base\Field;

use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

use \Symfony\Component\Validator\Constraints\Length;

final class PasswordField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/text')
            ->setFormType(RepeatedType::class)
            ->addCssClass('field-password')
            ->setFormTypeOptions([
                    'type' => PasswordType::class,
                    'first_options' => [
                        'label' => "New Password",
                        'attr' => [
                            "autocomplete" => "new-password"
                        ]

                    ],
                    'second_options' => [
                        'label' => "Confirm New Password",
                        'attr' => [
                            "autocomplete" => "new-password"
                        ]
                    ]
                ]);
    }
}
