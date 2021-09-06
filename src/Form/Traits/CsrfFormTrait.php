<?php

namespace Base\Form\Traits;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

use Base\Subscriber\CsrfSubscriber;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;

trait CsrfFormTrait
{
    public static function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id'   => null,
            'csrf_field_name' => '_csrf_token'
        ]);
    }

    public static function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options["csrf_protection"]) return;
        if (!$options["csrf_token_id"])
        throw new Exception("Undefined CSRF Token ID");

        $csrfManager = (array_key_exists("csrf_token_manager", $options)) ? $options["csrf_token_manager"] : null;
        if(!$csrfManager) throw new Exception("CSRF manager not available.. Form cannot be built.");

        $csrfToken = $csrfManager->getToken($options["csrf_token_id"]);
        $builder->add($options["csrf_field_name"], HiddenType::class, [ // (e.g. _csrf_token is defined/used in AuthenticatorForm)
                'mapped' => false,
                "attr" => ["value" => $csrfToken]
            ]);

        // ADD POST SUBMIT CHECK 
        $builder->addEventListener(FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
//                throw new InvalidCsrfTokenException();
            }
        );
    }
}
