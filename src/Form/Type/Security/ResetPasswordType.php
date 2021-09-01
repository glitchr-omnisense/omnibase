<?php

namespace Base\Form\Type\Security;

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

use Base\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Base\Form\Traits\CsrfFormTrait;
use Base\Form\Traits\BootstrapFormTrait;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class ResetPasswordType extends AbstractType
{
    use BootstrapFormTrait;
    use CsrfFormTrait;

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'csrf_token_id'   => 'reset-password',
            "translation_domain" => false
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('email', TextType::class, [
                'validation_groups' => ["new"],
                'mapped' => false,
            ]);

        parent::buildForm($builder, $options);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
    }
}
