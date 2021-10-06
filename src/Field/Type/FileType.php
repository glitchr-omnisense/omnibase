<?php

namespace Base\Field\Type;

use Base\Annotations\Annotation\Uploader;
use Base\Entity\User;
use Base\Field\Transformer\StringToFileTransformer;
use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FileType extends AbstractType implements DataMapperInterface
{
    public const KIB_BYTES = 1024;
    public const MIB_BYTES = 1048576;
    public const SUFFIXES = [
        1 => 'bytes',
        self::KIB_BYTES => 'KiB',
        self::MIB_BYTES => 'MiB',
    ];

    protected $baseService;
    protected $translator;

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
        return 'fileupload';
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'dropzone'     => [],
            'dropzone-js'  => $this->baseService->getParameterBag("base.vendor.dropzone.js"),
            'dropzone-css' => $this->baseService->getParameterBag("base.vendor.dropzone.css"),

            'allow_delete' => true,
            'required'     => false,
            'multiple'     => false,

            'sortable'     => true,
            'sortable-js'  => $this->baseService->getParameterBag("base.vendor.sortablejs.js"),

            'max_filesize' => null,
            'max_files'    => null,
            'mime_types'   => null,
        ]);

        $resolver->setAllowedTypes("dropzone", ['null', 'array']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $allowDelete = $options["allow_delete"];
        $isDropzone  = $options["dropzone"];
        $multiple    = $options["multiple"];

        $builder->add('file', HiddenType::class);
        if(!$isDropzone || !$multiple)
            $builder->add('raw', \Symfony\Component\Form\Extension\Core\Type\FileType::class, ["multiple" => $multiple]);
        if($allowDelete)
            $builder->add('delete', CheckboxType::class, ['required' => false]);

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($options) {

            $data = $event->getData();
            if(is_string($data)) {

                $cacheDir = $this->baseService->getCacheDir()."/dropzone";
                $data = explode("|", $data);

                foreach($data as $key => $uuid)
                    if(!empty($uuid)) $data[$key] = $cacheDir."/".$uuid;

                $data = !empty($data) ? array_map(fn ($fname) => (file_exists($fname) ? new UploadedFile($fname, $fname) : basename($fname)), $data): [];
                if(!$options["multiple"]) $data = $data[0] ?? null;
            }

            if(empty($data)) $data = null;
            $event->setData($data);
        });
        
        $builder->setDataMapper($this);
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
        //
            
        $parent = $form->getParent();
        $entity = $parent->getData();

        $files = Uploader::getFile($entity, $form->getName());
        if(!is_array($files)) $files = ($files ? [$files] : []);
        $view->vars['files'] = $files;

        $acceptedFiles = ($options["mime_types"] ? implode(",", $options["mime_types"]) : null);
        if(!$acceptedFiles && $entity) $acceptedFiles = implode(",", Uploader::getMimeTypes($options["data_class"], $form->getName()));
        $view->vars["accept"] = $acceptedFiles;

        $view->vars['value'] = Uploader::getPublicPath($options["data_class"], $form->getName());
        $view->vars['multiple']     = $options['multiple'];
        $view->vars['allow_delete'] = $options['allow_delete'];
        $view->vars['max_filesize'] = $options['max_filesize'];
        $view->vars['dropzone'] = ($options["dropzone"] !== null);
        if(is_array($options["dropzone"]) && $options["multiple"]) {

            if($options["dropzone-js"]) $this->baseService->addJavascriptFile($options["dropzone-js"]);
            if($options["dropzone-css"]) $this->baseService->addStylesheetFile($options["dropzone-css"]);

            $editor = $view->vars["id"]."_dropzone";
            $action = (!empty($options["action"]) ? $options["action"] : ".");
            
            $view->vars["attr"]["class"] = "dropzone";
            $view->vars["value"] = ""; // find existing file (todo)

            $dzOptions = $options["dropzone"];
            unset($dzOptions["init"]); // init is ignored..

            if(!array_key_exists("url", $dzOptions)) $dzOptions["url"] = $action;
            if($options['allow_delete'] !== null) $dzOptions["addRemoveLinks"] = $options['allow_delete'];
            if($options['max_filesize'] !== null) $dzOptions["max_filesize"]   = $options["max_filesize"];
            if($options['max_files']    !== null) $dzOptions["max_files"]      = $options["max_files"];
            if($acceptedFiles           !== null) $dzOptions["acceptedFiles"]  = $acceptedFiles;
            
            $dzOptions["dictDefaultMessage"] = $dzOptions["dictDefaultMessage"]
                ?? '<h4>'.$this->translator->trans2("messages.dropzone.title").'</h4><p>'.$this->translator->trans2("messages.dropzone.description").'</p>';
            
            $token = $this->csrfTokenManager->getToken("dropzone")->getValue();
            $postDelete = "/ux/dropzone/".$token."/'+file.serverId['uuid']+'/delete"; //ux_dropzone_delete

            $dzOptions["url"] = $this->baseService->getPath("ux_dropzone", ["token" => $token]);
            $dzOptions  = preg_replace(["/^{/", "/}$/"], ["", ""], json_encode($dzOptions));
            $dzOptions .= ",init:".$editor."_dropzoneInit";

            //
            // Default initialializer
            
            $this->baseService->addJavascriptCode(
                "<script>
                    Dropzone.autoDiscover = false;

                    function ".$editor."_dropzoneInit() {

                        this.on('success', function(file, response) {
                            file.serverId = response;
                            var val = $('#".$view->vars["id"]."').val();
                                val = (!val || val.length === 0 ? [] : val.split('|'));

                            val.push(file.serverId['uuid']);
                            $('#".$view->vars["id"]."').val(val.join('|'));

                            console.log(val);
                            // $.each(val, function(key,value) {
                            //     var mockFile = { name: value.name, size: value.size };
                    
                            //     myDropzone.emit('addedfile', mockFile);
                            //     myDropzone.emit('thumbnail', mockFile, value.path);
                            //     myDropzone.emit('complete', mockFile);
                    
                            // });
                        });

                        this.on('removedfile', function(file) {

                            if (!file.serverId) { return; }
                            $.post('$postDelete');

                            var val = $('#".$view->vars["id"]."').val();
                                val = (!val || val.length === 0 ? [] : val.split('|'));

                            const index = val.indexOf(file.serverId['uuid']);
                            if (index > -1) val.splice(index, 1);
                           
                            $('#".$view->vars["id"]."').val(val.join('|'));
                        });
                    }

                    let ".$editor." = new Dropzone('#".$editor."', {".$dzOptions."});
                </script>");

            if($options["sortable"]) {

                if($options["sortable-js"]) $this->baseService->addJavascriptFile($options["sortable-js"]);

                $this->baseService->addJavascriptCode(
                "<script>
                    var ".$editor."_sortable = new Sortable(document.getElementById('".$editor."'), {
                        draggable: '.dz-preview'
                    });

                    console.log(".$editor."_sortable, document.getElementById('".$editor."'));
                </script>");
            }

        } else {

            $this->baseService->addJavascriptCode(
                "<script>
                    $('#".$view->vars["id"]."_deleteBtn').on('click', function() {
                        $('#".$view->vars["id"]."_raw').val('');
                        $('#".$view->vars["id"]."_deleteBtn').css('display', 'none');
                    });
                    
                    $('#".$view->vars["id"]."_raw').on('change', function() {
                        if( $('#".$view->vars["id"]."_raw').val() !== '') $('#".$view->vars["id"]."_deleteBtn').css('display', 'block');
                        else $('#".$view->vars["id"]."_deleteBtn').css('display', 'none');
                    });
                </script>"
            );
        }
    }

    public function mapDataToForms($viewData, \Traversable $forms): void
    {
        // there is no data yet, so nothing to prepopulate
        if (null === $viewData) {
            return;
        }

        // invalid data type
        // if (!$viewData instanceof File) 
        //     $viewData = new File($viewData);

        $fileForm = current(iterator_to_array($forms));
        $fileForm->setData(basename($viewData));
    }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        $children = iterator_to_array($forms);
        $viewData = $children['file']->getData() ?? $children['raw']->getData() ?? [];
    }
}