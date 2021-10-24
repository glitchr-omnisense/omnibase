<?php

namespace Base\Field\Type;

use Base\Annotations\Annotation\Uploader;
use Base\Service\BaseService;
use InvalidArgumentException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ImageType extends AbstractType
{
    public function __construct(BaseService $baseService, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->baseService = $baseService;
        $this->translator  = $baseService->getTwigExtension();

        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'thumbnail'   => "/bundles/base/images.svg",

            'cropper'     => null,
            'cropper-js'  => $this->baseService->getParameterBag("base.vendor.cropperjs.js"),
            'cropper-css' => $this->baseService->getParameterBag("base.vendor.cropperjs.css")
        ]);

        $resolver->setAllowedTypes("cropper", ['null', 'array']);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'imageupload';
    }

    public function getParent()
    {
        return FileType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if($options["multiple"] && is_array($options["cropper"]))
            throw new InvalidArgumentException("There can be only one picture if you want to crop, please disable 'multiple' option");
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if(!($view->vars["accept"] ?? false) ) 
             $view->vars["accept"] = "image/*";

        $view->vars["thumbnail"] = $options["thumbnail"];

        $view->vars["cropper"] = null;
        $view->vars["ajaxPost"] = null;
        if(is_array($options["cropper"])) {

            $this->baseService->addHtmlContent("javascripts", $options["cropper-js"]);
            $this->baseService->addHtmlContent("stylesheets", $options["cropper-css"]);

            $token = $this->csrfTokenManager->getToken("dropzone")->getValue();

            if(!array_key_exists('viewMode', $options["cropper"])) $options["cropper"]['viewMode'] = 2;
            if(!array_key_exists('aspectRatio', $options["cropper"])) $options["cropper"]['aspectRatio'] = 1;

            $view->vars["cropper"]  = json_encode($options["dropzone"]);
            $view->vars["ajaxPost"] = "/ux/dropzone/".$token;
        }

        $this->baseService->addHtmlContent("javascripts:body", "/bundles/base/form-type-image.js");
    }
}
