<?php

namespace Base\Field\Type;

use Base\Enum\UserRole;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;


class RoleType extends AbstractType
{
    public function getParent() : ?string { return SelectType::class; }
    public function getBlockPrefix(): string { return 'role'; }

    public static function getChoices(): array
    {
        return [
            "Generics" => [

                "Super Administrator" => UserRole::SUPERADMIN,
                "Administrator" => UserRole::ADMIN,
                "User" => UserRole::USER
            ]
        ];
    }

    public function configureOptions(OptionsResolver $resolver) {

        $resolver->setDefaults([
            'multiple'     => true,
            'choices'      => self::getChoices(),
            // 'choice_icons' => self::getIcons(),
            // 'choice_attr'  => function (?string $entry) {
            //     return $entry ? ['data-icon' => self::getIcons()[$entry]] : [];
            // },
            'empty_data'   => UserRole::USER,
            'invalid_message' => function (Options $options, $previousValue) {
                    return 'Please select a role.';
            }
        ]);
    }
}
