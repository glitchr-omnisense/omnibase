<?php

namespace Base\Field\Type;

use InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImageType extends FileType
{
    public function getBlockPrefix(): string { return 'imageupload'; }
    public function getParent() : ?string { return FileType::class; }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'thumbnail'   => "bundles/base/images.svg",
            'alt'         => null,

            'cropper'     => null,
            'cropper-js'  => $this->baseService->getParameterBag("base.vendor.cropperjs.javascript"),
            'cropper-css' => $this->baseService->getParameterBag("base.vendor.cropperjs.stylesheet"),
            
            'mime_types'  => ["image/*"]
        ]);

        $resolver->setAllowedTypes("cropper", ['null', 'array']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if($options["alt"]   !== null) $builder->add("alt",   TextType::class, $options["alt"]);
        if($options["multiple"] && is_array($options["cropper"]))
            throw new InvalidArgumentException("There can be only one picture if you want to crop, please disable 'multiple' option");
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        if(!($view->vars["accept"] ?? false) ) 
             $view->vars["accept"] = "image/*";

        $view->vars["thumbnail"] = $this->baseService->getAsset($options["thumbnail"]);

        $view->vars["cropper"] = null;
        if(is_array($options["cropper"])) {

            $this->baseService->addHtmlContent("javascripts:head", $options["cropper-js"]);
            $this->baseService->addHtmlContent("stylesheets:head", $options["cropper-css"]);

            if(!array_key_exists('viewMode', $options["cropper"])) $options["cropper"]['viewMode'] = 2;
            if(!array_key_exists('aspectRatio', $options["cropper"])) $options["cropper"]['aspectRatio'] = 1;

            $view->vars["cropper"]  = json_encode($options["cropper"]);
        }

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-image.js");
    }
}