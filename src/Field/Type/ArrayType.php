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

        $resolver->setNormalizer('length',       fn(Options $options, $value) => $options["pattern"] ? $this->getNumberOfArguments($options["pattern"]) : $value);
        $resolver->setNormalizer('allow_add',    fn(Options $options, $value) => $options["length"] == 0 && $value);
        $resolver->setNormalizer('allow_delete', fn(Options $options, $value) => $options["length"] == 0 && $value);
    }

    public function getNumberOfArguments($pattern):int { return preg_match_all('/\{[0-9]*\}/i', $pattern); }
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
        $view->vars["pattern"] = $options["pattern"];
        // if($options["pattern"] !== null) {
        //     foreach($view as $childView) 
        //         $childView->vars['block_prefixes'] = array_filter($childView->vars['block_prefixes'], fn($b) => $b != "array_entry");
        // }

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-array.js");
    }
}
