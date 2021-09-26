<?php

namespace Base\Field\Type;

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

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'avatar';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'max_filesize' => null
        ]);
    }

    public function getParent()
    {
        return ImageType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if($options["multiple"])
            throw new InvalidArgumentException("There can be only one picture for avatar type, please disable 'multiple' option");
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
                        $('#".$id."_thumbnail').css('display', 'block');
                        $('#".$id."_figcaption').css('display', 'none');
                        $('#".$id."_image')[0].src = URL.createObjectURL(event.target.files[0]);
                    } else {
                        $('#".$id."_thumbnail').css('display', 'none');
                        $('#".$id."_figcaption').css('display', 'block');
                        $('#".$id."_image')[0].src = '/bundles/base/user.svg';
                    }
                }
            </script>");
    }
}