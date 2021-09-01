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

use Base\Form\AbstractType;
use Base\Form\Traits\RememberMeFormTrait;
use Base\Form\Traits\TargetPathFormTrait;
use Base\Form\Traits\CsrfFormTrait;
use Base\Form\Traits\BootstrapFormTrait;

class LoginType extends AbstractType
{
    use RememberMeFormTrait;
    use BootstrapFormTrait;
    use CsrfFormTrait;

    public function getBlockPrefix() { return ""; }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => User::class,
            'username' => null, // to pass variable from controller to Type
            'csrf_token_id'   => 'authenticate',
            'allow_extra_fields' => true,
            "translation_domain" => false
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
