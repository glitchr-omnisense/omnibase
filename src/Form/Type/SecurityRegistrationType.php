<?php

namespace Base\Form\Type;

use Base\Form\Model\SecurityRegistrationModel;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Util\StringUtil;

class SecurityRegistrationType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SecurityRegistrationModel::class
        ]);
    }

    public function getBlockPrefix():string { return "_base".StringUtil::fqcnToBlockPrefix(static::class) ?: ''; }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class)
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'validation_groups' => ['new']
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => true,
                'first_options'  => [
                    'validation_groups' => ["new"],
                    'attr' => [
                        "autocomplete" => "new-password"
                    ]
                ],
                'second_options' => [
                    'attr' => [
                        "autocomplete" => "new-password"
                    ]
                ],
            ]);
    }
}
