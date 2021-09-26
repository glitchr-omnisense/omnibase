<?php

namespace Base\Field\Type;

use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
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
            'cropper'     => false,
            'cropper-js'  => $this->baseService->getParameterBag("base.vendor.cropper.js"),
            'cropper-css' => $this->baseService->getParameterBag("base.vendor.cropper.css"),
        ]);
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

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $id = $view->vars["id"];
        
        $view->vars["file"]["attr"]["accept"] = "image/*"; 

        $view->vars["file"]["attr"]["onchange"] 
            = ($view->vars["file"]["attr"]["onchange"] ?? "")." ".$id."_updatePreview();";
        $view->vars["deleteOpt"]["attr"]["onclick"] 
            = ($view->vars["deleteOpt"]["attr"]["onclick"] ?? "")." ".$id."_updatePreview();";
        $this->baseService->addJavascriptCode(
            "<script>
                function ".$id."_updatePreview() {
                    
                    if( $('#".$id."_file').val() !== '') {
                        $('#".$id."_image')[0].src = URL.createObjectURL(event.target.files[0]);
                    } else {
                        $('#".$id."_image')[0].src = '/bundles/base/images.svg';
                    }
                }
            </script>");
    }
}