<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Entity\Layout\ImageCrop;
use Base\Form\FormFactory;
use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
    
    public function __construct(ClassMetadataManipulator $classMetadataManipulator, FormFactory $formFactory, BaseService $baseService) 
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->formFactory = $formFactory;
        $this->baseService = $baseService;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "data_class" => ImageCrop::class,
            "label" => false,
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

            "fields" => [
                "label"    => [],
                "x"        => [],
                "y"        => [],
                "width"    => [],
                "height"   => [],
                "scaleX"   => [],
                "scaleY"   => [],
                "rotate"   => [],
            ],

            "aspectRatios" => [
                "@fields.cropper.aspect_ratio.standard"  => 4/3,
                "@fields.cropper.aspect_ratio.large"     => 16/9,
                "@fields.cropper.aspect_ratio.square"    => 1,
                "@fields.cropper.aspect_ratio.facebook"  => 1200/630,  # > 16:9
                "@fields.cropper.aspect_ratio.pinterest" => 1000/1500, # 2:3
                "@fields.cropper.aspect_ratio.free"      => NAN
            ],

            "default_fields" => [

                "label"  => ["label" => "Label"  , "required" => false, "form_type" => TextType::class],
                "x"      => ["label" => "Left"   , "form_type" => HiddenType::class],
                "y"      => ["label" => "Top"    , "form_type" => HiddenType::class],
                "width"  => ["label" => "Width"  , "form_type" => HiddenType::class],
                "height" => ["label" => "Height" , "form_type" => HiddenType::class],
                "scaleX" => ["label" => "Scale X", "form_type" => HiddenType::class],
                "scaleY" => ["label" => "Scale Y", "form_type" => HiddenType::class],
                "rotate" => ["label" => "Rotate" , "form_type" => HiddenType::class]
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

            $fields = array_keys($options["fields"]);
            foreach($fields as $parameter) {

                $formOptions = array_merge(
                    $options["default_fields"][$parameter],
                    $options["fields"][$parameter]
                );
                
                $formType = array_pop_key("form_type", $formOptions) ?? HiddenType::class;
                $form->add($parameter, $formType, $formOptions);
            }
        });
    }

    public function mapDataToForms($viewData, Traversable $forms) {

        if($viewData === null) return;

        $form = current(iterator_to_array($forms));
        $options = $form->getParent()->getConfig()->getOptions();
        $classMetadata = $this->classMetadataManipulator->getClassMetadata($options["data_class"]);
        foreach(iterator_to_array($forms) as $formName => $form) {
            $form->setData($classMetadata->getFieldValue($viewData, $formName, $form->getData()));
        }
    }

    public function mapFormsToData(Traversable $forms, &$viewData)
    {
        $form = current(iterator_to_array($forms));
        $options = $form->getParent()->getConfig()->getOptions();
        
        $classMetadata = $this->classMetadataManipulator->getClassMetadata($options["data_class"]);
        foreach(iterator_to_array($forms) as $formName => $form)
            $classMetadata->setFieldValue($viewData, $formName, $form->getData());
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["cropper"]      = json_encode($options["cropper"]);
        $view->vars["aspectRatios"] = $options["aspectRatios"];

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
                throw new \Exception("Child form \"$path\" related to view data \"".$target->vars["name"]."\" not found in ".get_class($form->getConfig()->getType()->getInnerType())." (complete path: \"".$options["target"]."\")");

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
