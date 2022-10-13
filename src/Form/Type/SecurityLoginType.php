<?php

namespace Base\Form\Type;

use Base\Form\Common\AbstractType;
use Base\Form\Model\SecurityLoginModel;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

class SecurityLoginType extends AbstractType
{
    public function getBlockPrefix() : string { return "security_login"; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SecurityLoginModel::class,
            'identifier' => null, // to pass variable from controller to Type
            'allow_extra_fields' => true
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // $this->builder->getForm()->addStep($options, function (FormBuilderInterface $builder, array $options) {

        //     $builder->add('excel', FileType::class, [
        //             'label' => "Fichier Excel",
        //             'attr'  => [
        //                 'id' => "inputExcel",  // used in Symfony kernel
        //             ]
        //         ]);
        // });

        // $this->builder->getForm()->addConfirmStep($options);

        $builder
            ->add('identifier', TextType::class, [
                'attr'  => [
                    'id' => "inputUsername",  // used in Symfony kernel
                    'value' => $options["identifier"] ?? ""
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
