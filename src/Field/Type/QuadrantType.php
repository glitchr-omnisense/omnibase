<?php

namespace Base\Field\Type;

use Base\Enum\Quadrant\Quadrant;
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

class QuadrantType extends AbstractType implements DataMapperInterface
{
    public function getBlockPrefix(): string { return 'quadrant'; }

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

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDataMapper($this);
        $builder->add("wind", HiddenType::class);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $view->vars['positions']  = json_encode($options["class"]::getPositions());
        $view->vars['quadrants']  = $options["class"]::getPermittedValues();
        $view->vars['icons']      = $options["class"]::getIcons();
        $view->vars['default']    = $options["class"]::getDefault();
        
        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-quadrant.js");
    }

    public function mapDataToForms($viewData, Traversable $forms) { }
    public function mapFormsToData(Traversable $forms, &$viewData)
    {
        $windType = current(iterator_to_array($forms));
        $viewData = $windType->getData() ?? Quadrant::O;

    }
}