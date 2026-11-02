<?php

namespace Base\Form\Definition;

use Base\Field\Kind;
use Base\Form\Model\SecurityLoginModel;
use Base\Form\Model\SecurityLoginTokenModel;
use Base\Form\Model\SecurityRegistrationModel;
use Base\Form\Model\UserProfileModel;

/**
 * The account's forms, neutral (plan "omnibase agnostic", step F5b): what omnibridge/symfony's types
 * build, what a generic host renders and processes. A site adds its own fields to them.
 */
final class AccountForms
{
    /** The Symfony form type of the two inputs of a sign-up's password: the plain one, no strength meter. */
    public const PLAIN_PASSWORD_TYPE = 'Symfony\Component\Form\Extension\Core\Type\PasswordType';

    private function __construct()
    {
    }

    /**
     * The sign-in: its fields, posted to whatever answers it (the firewall's authenticator).
     *
     * @param string|null $identifier what was typed before, shown again
     */
    public static function signIn(?string $identifier = null, string $name = '_base_security_login'): FormDefinition
    {
        return FormDefinition::new($name)->setDataClass(SecurityLoginModel::class)
            ->add(FormDefinition::field('identifier', Kind::TEXT)->setFormTypeOption('attr', ['id' => 'inputUsername', 'value' => $identifier ?? '']))
            // Symfony's own: the sign-in has no strength meter.
            ->add(FormDefinition::field('password', Kind::PASSWORD)->setFormType(self::PLAIN_PASSWORD_TYPE)->setFormTypeOption('attr', ['id' => 'inputPassword']))
            ->add(FormDefinition::field('_remember_me', Kind::CHECKBOX)->setVirtual(true)->setRequired(false)->setFormTypeOption('attr', ['checked' => 'checked']));
    }

    /** The sign-in by a link: an address. A trap, the time, the captcha; no list: the same answer for every address. */
    public static function signInLink(?string $email = null, string $name = '_base_security_login_token'): FormDefinition
    {
        return FormDefinition::new($name)->setDataClass(SecurityLoginTokenModel::class)
            ->setGuard(['action' => 'login_link', 'reputation' => false])
            ->add(FormDefinition::field('email', Kind::TEXT)->setFormTypeOption('attr', ['id' => 'inputEmail', 'value' => $email ?? '']));
    }

    /** The sign-up: an address, the terms, a password twice; a trap, the time, the lists, the captcha. */
    public static function signUp(string $name = '_base_security_registration'): FormDefinition
    {
        return FormDefinition::new($name)->setDataClass(SecurityRegistrationModel::class)
            ->setGuard(['action' => 'signup'])
            ->add(FormDefinition::field('email', Kind::EMAIL))
            ->add(FormDefinition::field('agreeTerms', Kind::CHECKBOX)->setVirtual(true))
            ->add(FormDefinition::field('plainPassword', Kind::REPEATED)->setFormTypeOptions([
                'type' => Kind::PASSWORD,
                'required' => true,
                'first_options' => ['attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['attr' => ['autocomplete' => 'new-password']],
            ])->setCustomOption('symfony.repeated_type', self::PLAIN_PASSWORD_TYPE));
    }

    /** The profile's editor: the avatar, the address, a new password. */
    public static function profile(string $name = '_base_user_profile'): FormDefinition
    {
        return FormDefinition::new($name)->setDataClass(UserProfileModel::class)
            ->add(FormDefinition::field('avatar', Kind::AVATAR)->setRequired(false)->setFormTypeOption('cropper', []))
            ->add(FormDefinition::field('email', Kind::EMAIL)->setRequired(false))
            ->add(FormDefinition::field('plainPassword', Kind::PASSWORD)->setRequired(false));
    }
}
