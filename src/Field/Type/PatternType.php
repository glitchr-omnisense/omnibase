<?php

namespace Base\Field\Type;

use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PatternType extends AbstractType
{
    public function __construct(BaseService $baseService) {

        $this->baseService = $baseService;
    }

    public function getBlockPrefix(): string
    {
        return 'pattern';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "pattern" => null
        ]);
    }

    public function getNumberOfArguments($options):int { return preg_match_all('/\{[0-9]*\}/i', $options["pattern"]); }
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["pattern"] = $options["pattern"];
        
        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-pattern.js");
    }

    public function getParent() : ?string
    {
        return TextType::class;
    }
}
