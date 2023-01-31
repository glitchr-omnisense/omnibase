<?php

namespace Base\Field\Type;

use Base\Twig\Environment;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NumberType extends \Symfony\Component\Form\Extension\Core\Type\NumberType
{
    /**
     * @var Environment
     */
    protected $twig;

    public function getBlockPrefix(): string { return 'number2'; }
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars["stepUp"]       = $options["stepUp"] ?? $options["step"];
        $view->vars["stepDown"]     = $options["stepDown"] ?? $options["step"];
        $view->vars["keyUp"]        = $options["keyUp"];
        $view->vars["keyDown"]      = $options["keyDown"];
        $view->vars["throttleUp"]   = $options["throttleUp"] ?? $options["throttle"];
        $view->vars["throttleDown"] = $options["throttleDown"] ?? $options["throttle"];
        $view->vars["min"]          = $options["min"];
        $view->vars["max"]          = $options["max"];
        $view->vars["disabled"]     = $options["disabled"];
        $view->vars["autocomplete"] = $options["autocomplete"];
        $view->vars["value"]        = $form->getData() ?? $options["value"] ?? 0;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'stepUp'  => null,
            'stepDown' => null,
            'step' => 5,
            'throttleUp'  => null,
            'throttleDown' => null,
            'throttle' => 10,
            "min" => null,
            "max" => null,
            "autocomplete" => false,
            "keyUp" => true,
            "keyDown" => true,
            "value" => 0
        ]);
    }

}
