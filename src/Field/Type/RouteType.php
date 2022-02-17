<?php

namespace Base\Field\Type;

use Base\Field\Type\SelectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class RouteType extends AbstractType
{
    public function getParent(): ?string { return SelectType::class; }
    public function getBlockPrefix(): string { return 'route'; }

    public function __construct(RouterInterface $router) { $this->router = $router; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choice_loader' => function (Options $options) {

                $routeList = [];
                return ChoiceList::loader($this, new CallbackChoiceLoader(function () {

                    return array_flip(array_transforms(
                        fn($k, $r):array => [$k, "<b>Name:</b> ".$k." | <b>Path:</b> ".$r->getPath()], 
                        $this->router->getRouteCollection()->all(),
                    ));

                }), $routeList);
            },
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
            'invalid_message' => function (Options $options, $previousValue) {
                return ($options['legacy_error_messages'] ?? true)
                    ? $previousValue
                    : 'Please select a valid currency.';
            },
        ]);

        $resolver->setAllowedTypes('choice_translation_locale', ['null', 'string']);
    }
}
