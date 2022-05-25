<?php

namespace Base\Field\Type;

use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockType extends NumberType
{
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars['allow_infinite'] = $options["allow_infinite"];
        $view->vars["stepUp"]   = $options["stepUp"];
        $view->vars["stepDown"] = $options["stepDown"];

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-stock.js");
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'allow_infinite' => true,
            'stepUp'  => 1,
            'stepDown' => 1
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string { return 'stock'; }
}
