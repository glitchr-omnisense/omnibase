<?php

namespace Base\Form\Type\Security;

use App\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class LoginType extends AbstractType
{
    public function getBlockPrefix() : string { return "login"; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'username' => null, // to pass variable from controller to Type
            'allow_extra_fields' => true
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', TextType::class, [
                'label' => "Username",
                'attr'  => [
                    'id' => "inputUsername",  // used in Symfony kernel
                    'value' => $options["username"] ?? $options["email"] ?? ""
                ]
            ])
            ->add('password', PasswordType::class, [
                'attr'  => [
                    'id' => "inputPassword"  // used in Symfony kernel
                ]
            ])
            ->add("_remember_me", CheckboxType::class, [
                    'mapped' => false,
                    'required' => false,
                    'attr' => ["checked" => "checked"]
            ]);
    }
}
