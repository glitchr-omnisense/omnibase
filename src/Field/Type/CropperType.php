<?php

namespace Base\Field\Type;

use Base\Service\BaseService;
use InvalidArgumentException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CropperType extends AbstractType
{
    public function getBlockPrefix(): string { return 'cropper'; }
    
    public function __construct(BaseService $baseService) 
    {
        $this->baseService = $baseService;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'cropper'     => null,
            'cropper-js'  => $this->baseService->getParameterBag("base.vendor.cropperjs.javascript"),
            'cropper-css' => $this->baseService->getParameterBag("base.vendor.cropperjs.stylesheet"),
        ]);

        $resolver->setAllowedTypes("cropper", ['null', 'array']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["cropper"] = null;
        if(is_array($options["cropper"])) {

            $this->baseService->addHtmlContent("javascripts:head", $options["cropper-js"]);
            $this->baseService->addHtmlContent("stylesheets:head", $options["cropper-css"]);

            if(!array_key_exists('viewMode', $options["cropper"])) $options["cropper"]['viewMode'] = 2;
            if(!array_key_exists('aspectRatio', $options["cropper"])) $options["cropper"]['aspectRatio'] = 1;

            $view->vars["cropper"]  = json_encode($options["cropper"]);
        }

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-cropper.js");
    }
}
