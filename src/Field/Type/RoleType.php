<?php

namespace Base\Field\Type;

use Base\Entity\User;
use Base\Enum\UserRole;
use Base\Field\Traits\SelectTypeInterface;
use Base\Field\Traits\SelectTypeTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;


class RoleType extends AbstractType implements SelectTypeInterface
{
    use SelectTypeTrait;
    
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

    public static function getIcons(): array
    {
        return [
            UserRole::SUPERADMIN => "fas fa-fw fa-crown",
            UserRole::ADMIN => "fas fa-fw fa-star",
            UserRole::USER => "fas fa-fw fa-user",
        ];
    }

    public static function getAltIcons(): array
    {
        return [
            UserRole::SUPERADMIN => "fas fa-fw fa-user-cog",
            UserRole::ADMIN => "fas fa-fw fa-user-check",
            UserRole::USER => "fas fa-fw fa-tags",
        ];
    }

    public function configureOptions(OptionsResolver $resolver) {

        $resolver->setDefaults([
            'multiple'     => true,
            'choices'      => self::getChoices(),
            'choice_icons' => self::getIcons(),
            'choice_attr'  => function (?string $entry) {
                return $entry ? ['data-icon' => self::getIcons()[$entry]] : [];
            },
            'empty_data'   => UserRole::USER,
            'invalid_message' => function (Options $options, $previousValue) {
                    return 'Please select a role.';
            }
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent() : ?string
    {
        return SelectType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'role';
    }
}
