<?php

namespace Base\Field\Type;

use Base\Annotations\Annotation\Uploader;
use Base\Service\BaseService;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class AvatarType extends AbstractType
{
    public function __construct(BaseService $baseService, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->baseService = $baseService;
        $this->translator  = $baseService->getTwigExtension();

        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function getBlockPrefix()
    {
        return 'avatar';
    }

    public function getParent()
    {
        return ImageType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'thumbnail'   => "bundles/base/user.svg",
            'cropper'     => null
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if($options["multiple"])
            throw new InvalidArgumentException("There can be only one picture for avatar type, please disable 'multiple' option");
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        //
        // VIEW: 
        // - <id>_raw  = file,
        // - <id>_file = hidden,
        // - <id>_deleteBtn = btn "x",
        // - <id>_deleteAvatarBtn = btn "x",
        // - <id>_figcaption = btn "+"
        // - dropzone: <id>_dropzone = btn "x",
        // - cropper: <id>_modal     = modal
        // - cropper: <id>_cropper   = cropper
        // - cropper: <id>_thumbnail = thumbnail
        //
        $view->vars['avatar'] = $view->vars['files'][0] ?? null;
        $view->vars['files']  = [];

        if(!($view->vars["accept"] ?? false) ) 
             $view->vars["accept"] = "image/*";

        $view->vars["thumbnail"] = $options["thumbnail"];
        $view->vars["cropper"] = ($options["cropper"] !== null);

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-avatar.js");
    }
}
