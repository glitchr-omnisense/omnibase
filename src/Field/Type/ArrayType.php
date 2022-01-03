<?php

namespace Base\Field\Type;

use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArrayType extends CollectionType
{
    public function getBlockPrefix(): string { return 'array'; }
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            "pattern" => null,
            "length" => 0
        ]);
    }

    public function getNumberOfArguments($options):int { return preg_match_all('/\{[0-9]*\}/i', $options["pattern"]); }
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
        $view->vars["pattern"] = $options["pattern"];
        
        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-array.js");
    }
}
