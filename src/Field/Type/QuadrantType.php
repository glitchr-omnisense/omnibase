<?php

namespace Base\Field\Type;

use Base\Enum\Quadrant\Quadrant8;
use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Traversable;

class QuadrantType extends AbstractType
{
    public function getBlockPrefix(): string { return 'quadrant'; }
    public function getParent() : ?string { return HiddenType::class; }

    public function __construct(BaseService $baseService) 
    {
        $this->baseService = $baseService;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "class" => Quadrant8::class
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $view->vars['quadrants'] = $options["class"]::getPermittedValues();
        $view->vars['icons'] = $options["class"]::__iconizeStatic();

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-quadrant.js");
    }
}
