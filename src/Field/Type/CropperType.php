<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Entity\Layout\ImageCrop;
use Base\Enum\Quadrant\Quadrant8;
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
use Symfony\Component\PropertyAccess\PropertyAccess;


class CropperType extends AbstractType implements DataMapperInterface
{
    public function getBlockPrefix(): string { return 'cropper'; }

    public function __construct(FormFactory $formFactory, BaseService $baseService)
    {
        $this->formFactory = $formFactory;
        $this->baseService = $baseService;

        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
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

            "quadrant"          => null,
            "target"         => null,
            "natural_width"  => null,
            "natural_height" => null,

            "fields" => null,

            "aspectRatios" => [
                "@fields.cropper.aspect_ratio.standard"  => 4/3,
                "@fields.cropper.aspect_ratio.large"     => 16/9,
                "@fields.cropper.aspect_ratio.square"    => 1,
                "@fields.cropper.aspect_ratio.facebook"  => 1200/630,  # > 16:9
                "@fields.cropper.aspect_ratio.pinterest" => 1000/1500, # 2:3
            ],
        ]);

        $resolver->setAllowedTypes('target', ['string', 'null']);
        $resolver->setAllowedTypes("cropper", ['null', 'array']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {

            $form = $event->getForm();

            $fields = array_reverse(array_merge(array_reverse([

                // Displayed variables
                "x"      => ["label"  => "Left"   , "form_type" => NumberType::class, "min" => -1],
                "y"      => ["label"  => "Top"    , "form_type" => NumberType::class, "min" => -1],
                "width"  => ["label"  => "Width"  , "form_type" => NumberType::class, "min" => -1],
                "height" => ["label"  => "Height" , "form_type" => NumberType::class, "min" => -1],

                // Not implemented for the moment
                // "rotate"  => ["label"  => "Rotate" , "form_type" => HiddenType::class],
                // "scaleX"  => ["label"  => "Scale X", "form_type" => HiddenType::class],
                // "scaleY"  => ["label"  => "Scale Y", "form_type" => HiddenType::class],
                // "pivotX"      => ["label"  => "Pivot X", "form_type" => HiddenType::class],
                // "pivotY"      => ["label"  => "Pivot Y", "form_type" => HiddenType::class],

                // Behind the scene
                "x0"             => ["form_type" => HiddenType::class, "label" => "Left (normalized)"   ],
                "y0"             => ["form_type" => HiddenType::class, "label" => "Top (normalized)"    ],
                "width0"         => ["form_type" => HiddenType::class, "label" => "Width (normalized)"  ],
                "height0"        => ["form_type" => HiddenType::class, "label" => "Height (normalized)" ],
                "xP"             => ["form_type" => HiddenType::class, "label" => "Pivot X (normalized)"],
                "yP"             => ["form_type" => HiddenType::class, "label" => "Pivot Y (normalized)"],

            ]), array_reverse($options["fields"]) ?? []));

            foreach($fields as $fieldName => $fieldOptions) {

                $fieldType = array_pop_key("form_type", $fieldOptions) ?? HiddenType::class;
                $form->add($fieldName, $fieldType, $fieldOptions);
                dump($fieldOptions);
            }
        });
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

        //
        // Check if target path is reacheable..
        if(str_contains($options["target"], "/")) $targetPath = $options["target"];
        else {

            if(str_starts_with($options["target"], ".")) $target = substr($options["target"], 1);
            else $target = $ancestor;

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
        }

        $view->vars['target'] = $targetPath;

        //
        // Check if quadrant path is reacheable..
        if(in_array($options["quadrant"], Quadrant8::getPermittedValues())) $quadrantPath = $options["quadrant"];
        else {

            $quadrant = $ancestor;
            $quadrantPath = $options["quadrant"] ? explode(".", $options["quadrant"]) : null;
            foreach($quadrantPath ?? [] as $path) {

                if(!array_key_exists($path, $quadrant->children))
                    throw new \Exception("Child form \"$path\" related to view data \"".$quadrant->vars["name"]."\" not found in ".get_class($form->getConfig()->getType()->getInnerType())." (complete path: \"".$options["quadrant"]."\")");

                $quadrant = $quadrant->children[$path];
                if($quadrant->vars["name"] == "translations") {

                    $availableLocales = array_keys($quadrant->children);
                    $locale = count($availableLocales) > 1 ? $quadrant->vars["default_locale"]: first($availableLocales) ?? null;
                    if($locale) $quadrant = $quadrant->children[$locale];
                }
            }
        }

        $view->vars['quadrant'] = $quadrantPath;
    }

    public function mapDataToForms($viewData, Traversable $forms)
    {
        if($viewData === null) return;

        foreach(iterator_to_array($forms) as $formName => $form)
            $form->setData($this->propertyAccessor->getValue($viewData, $formName));
    }

    public function mapFormsToData(Traversable $forms, &$viewData)
    {
        foreach(iterator_to_array($forms) as $formName => $form) {
            dump($form, $formName, $form->getData());
            $this->propertyAccessor->setValue($viewData, $formName, $form->getData());
        }

        dump($viewData);
        exit(1);
    }
}
