<?php

namespace Base\Form\Type\Security;

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Base\Form\Traits\CsrfFormTrait;
use Base\Form\Traits\BootstrapFormTrait;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class ResetPasswordConfirmType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'validation_groups' => ["edit"],
            'mapped' => false]);    
    }
}
