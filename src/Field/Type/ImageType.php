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
            'thumbnail'   => "bundles/base/images.svg",

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
        //
        // VIEW: 
        // - <id>_raw  = file,
        // - <id>_file = hidden,
        // - <id>_deleteBtn = btn "x",
        // - <id>_figcaption = btn "+"
        // - dropzone: <id>_dropzone = btn "x",
        // - cropper: <id>_modal     = modal
        // - cropper: <id>_cropper   = cropper
        // - cropper: <id>_thumbnail = thumbnail
        //

        if(!($view->vars["accept"] ?? false) ) 
             $view->vars["accept"] = "image/*";

        $view->vars["cropper"] = ($options["cropper"] !== null);
        $view->vars["thumbnail"] = $options["thumbnail"];

        if(is_array($options["cropper"])) {

            $this->baseService->addHtmlContent("javascripts", $options["cropper-js"]);
            $this->baseService->addHtmlContent("stylesheets", $options["cropper-css"]);

            $token = $this->csrfTokenManager->getToken("dropzone")->getValue();

            $post = $this->baseService->getPath("ux_dropzone", ["token" => $token]);
            $postDelete = "/ux/dropzone/".$token."/'+file+'/delete"; //ux_dropzone_delete

            $cropperOptions = $options["cropper"];
            if(!array_key_exists('viewMode', $cropperOptions)) $cropperOptions['viewMode'] = 2;
            if(!array_key_exists('aspectRatio', $cropperOptions)) $cropperOptions['aspectRatio'] = 1;
        
            $this->baseService->addHtmlContent("javascripts:body", 
            "<script>

                var ".$view->vars["id"]."_cropper;
                var ".$view->vars["id"]."_blob;

                // Image processing
                $('#".$view->vars["id"]."_modal').on('shown.bs.modal', function () { 
                    ".$view->vars["id"]."_cropper = new Cropper($('#".$view->vars["id"]."_cropper')[0], ".json_encode($cropperOptions)."); 
                }).on('hidden.bs.modal', function () { 
                    ".$view->vars["id"]."_cropper.destroy(); 
                });

                $('#".$view->vars["id"]."_deleteBtn').on('click', function () {
                
                    var file = $('#".$view->vars["id"]."_file').val();
                    if(file !== '') $.post('".$postDelete."');
                });

                $('.".$view->vars["id"]."_modalClose').on('click', function () {

                    $('#".$view->vars["id"]."_modal').modal('hide');
                    $('#".$view->vars["id"]."_file').val(".$view->vars["id"]."_blob);
                    $('#".$view->vars["id"]."_thumbnail').val(".$view->vars["id"]."_blob);

                    if ($('#".$view->vars["id"]."_file').val() === '')
                        $('#".$view->vars["id"]."_deleteBtn').click();
                });

                $(document).on('keypress',function(e) {
                    if(e.which == 13 && $('#".$view->vars["id"]."_raw').val() !== '')
                        $('#".$view->vars["id"]."_modalSave').trigger('click');
                });

                $('#".$view->vars["id"]."_modalSave').on('click', function () {
                    
                    $('#".$view->vars["id"]."_modal').modal('hide');
                    if (".$view->vars["id"]."_cropper) {

                        var canvas = ".$view->vars["id"]."_cropper.getCroppedCanvas({width: 160, height: 160});
                        $('#".$view->vars["id"]."_thumbnail')[0].src = canvas.toDataURL();

                        canvas.toBlob(function (blob) {

                            var formData = new FormData();

                            var file = $('#".$view->vars["id"]."_file').val();
                            if(file !== '') $.post('".$postDelete."');

                            formData.append('file', blob, $('#".$view->vars["id"]."_raw').val());
                            ".$view->vars["id"]."_blob = blob;

                            $.ajax('$post', {
                                method: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,

                                success: function (file) { $('#".$view->vars["id"]."_file').val(file.uuid).trigger('change'); },
                                error: function (file) { $('#".$view->vars["id"]."_thumbnail')[0].src = '".$options["thumbnail"]."'; },
                                complete: function () { },
                            });
                        });
                    }
                });
            </script>");
        }

        if(is_array($options["cropper"])) {

            $this->baseService->addHtmlContent("javascripts:body", 
            "<script>

                $('#".$view->vars["id"]."_thumbnail').on('click', function() {
                    if($('#".$view->vars["id"]."_raw').val() === '') $('#".$view->vars["id"]."_raw').click();
                    else $('#".$view->vars["id"]."_modal').modal('show');
                });
    
                $('#".$view->vars["id"]."_deleteBtn').on('click', function() {
                    $('#".$view->vars["id"]."_thumbnail')[0].src = '".$options["thumbnail"]."';
                    $('#".$view->vars["id"]."_raw').val('');
                    $('#".$view->vars["id"]."_raw').change();
                });

                $('#".$view->vars["id"]."_raw').on('change', function() {
        
                    if( $('#".$view->vars["id"]."_raw').val() !== '') {
        
                        $('#".$view->vars["id"]."_modal').modal('show'); 
                        $('#".$view->vars["id"]."_figcaption').css('display', 'none');
                        $('#".$view->vars["id"]."_cropper')[0].src = URL.createObjectURL(event.target.files[0]);
        
                    } else {
        
                        $('#".$view->vars["id"]."_file').val('');
                        $('#".$view->vars["id"]."_figcaption').css('display', 'flex');
                        $('#".$view->vars["id"]."_cropper')[0].src = '".$options["thumbnail"]."';
                    }
                });
                </script>");

        } else {

            $this->baseService->addHtmlContent("javascripts:body", 
            "<script>
                $('#".$view->vars["id"]."_raw').on('change', function() {
            
                    if( $('#".$view->vars["id"]."_raw').val() !== '') {
        
                        $('#".$view->vars["id"]."_figcaption').css('display', 'none');
                        $('#".$view->vars["id"]."_thumbnail')[0].src = URL.createObjectURL(event.target.files[0]);
        
                    } else {
        
                        $('#".$view->vars["id"]."_file').val('');
                        $('#".$view->vars["id"]."_figcaption').css('display', 'flex');
                        $('#".$view->vars["id"]."_thumbnail')[0].src = '".$options["thumbnail"]."';
                    }
                });
            </script>");
        }

        $this->baseService->addHtmlContent("javascripts:body", 
        "<script>
        $('#".$view->vars["id"]."_figcaption').on('click', function() {
            $('#".$view->vars["id"]."_raw').click();
        });

        $('#".$view->vars["id"]."_deleteBtn').on('click', function() {
            $('#".$view->vars["id"]."_thumbnail')[0].src = '".$options["thumbnail"]."';
            $('#".$view->vars["id"]."_raw').change();
        });
        </script>");
    }
}
