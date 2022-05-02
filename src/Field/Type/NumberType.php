<?php

namespace Base\Field\Type;

use Base\Service\BaseService;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NumberType extends \Symfony\Component\Form\Extension\Core\Type\NumberType
{
    public function getBlockPrefix(): string { return 'number2'; }
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }
    
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars["stepUp"]   = $options["stepUp"] ?? $options["step"];
        $view->vars["stepDown"] = $options["stepDown"] ?? $options["step"];
        $view->vars["min"]      = $options["min"];
        $view->vars["max"]      = $options["max"];
        $view->vars["disabled"]      = $options["disabled"];
        
        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-number.js");
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'stepUp'  => null,
            'stepDown' => null,
            'step' => 1,
            "min" => null,
            "max" => null
        ]);
    }
    
}
