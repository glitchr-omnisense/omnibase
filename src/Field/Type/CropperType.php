<?php

namespace Base\Field\Type;

use Base\Form\FormFactory;
use Base\Service\BaseService;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Traversable;

class CropperType extends AbstractType implements DataMapperInterface
{
    public function getBlockPrefix(): string { return 'cropper'; }
    
    public function __construct(BaseService $baseService) 
    {
        $this->baseService = $baseService;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'cropper'     => [
                "dragMode"     => "none",
                "responsive"   => true,
                "movable"      => false,
                "zoomable"     => false,
                "restore"      => false,
                "viewMode"     => 2,
                "autoCropArea" => 1,
                "center"       => true,
            ],

            'cropper-js'  => $this->baseService->getParameterBag("base.vendor.cropperjs.javascript"),
            'cropper-css' => $this->baseService->getParameterBag("base.vendor.cropperjs.stylesheet"),

            "target"        => null,

            "parameters" => [
                "x"     => [],
                "y"      => [],
                "width"    => [],
                "height"   => [],
                "scaleX"   => [],
                "scaleY"   => [],
                "rotate"   => [],
            ]
        ]);

        $resolver->setAllowedTypes('target', ['string', 'null']);
        $resolver->setAllowedTypes("cropper", ['null', 'array']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {

            $form = $event->getForm();

            $parameters = array_keys($options["parameters"]);
            foreach($parameters as $parameter) {
                $formType = array_pop_key("form_type", $options["parameters"][$parameter]) ?? HiddenType::class;
                $form->add($parameter , $formType, $options["parameters"][$parameter]);
            }
        });
    }

    public function mapDataToForms($viewData, Traversable $forms) {

        foreach(iterator_to_array($forms) as $form)
            $form->setData($viewData[$form->getName()] ?? null);
    }

    public function mapFormsToData(Traversable $forms, &$viewData)
    {
        foreach(iterator_to_array($forms) as $form) {

            $viewData[$form->getName()] = (int) $form->getViewData();
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["cropper"]  = json_encode($options["cropper"]);
        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-cropper.js");
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        // Get oldest parent form available..
        $ancestor = $view;
        while($ancestor->parent !== null)
            $ancestor = $ancestor->parent; 

        $view->ancestor = $ancestor;

        // Check if path is reacheable..
        $target = $ancestor;
        $targetPath = $options["target"] ? explode(".", $options["target"]) : null;
        foreach($targetPath ?? [] as $path) {

            if(!array_key_exists($path, $target->children))
                throw new \Exception("Child form \"$path\" related to view data \"".get_class($target->vars["name"])."\" not found in ".get_class($form->getConfig()->getType()->getInnerType())." (complete path: \"".$options["target"]."\")");

            $target = $target->children[$path];
            if($target->vars["name"] == "translations") {

                $availableLocales = array_keys($target->children);
                $locale = count($availableLocales) > 1 ? $target->vars["default_locale"]: first($availableLocales) ?? null;
                if($locale) $target = $target->children[$locale];
            }
        }
        
        $view->vars['target'] = $targetPath;
    }
}
