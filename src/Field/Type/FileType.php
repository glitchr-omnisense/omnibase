<?php

namespace Base\Field\Type;

use Base\Annotations\Annotation\Uploader;
use Base\Entity\User;
use Base\Field\Transformer\StringToFileTransformer;
use Base\Service\BaseService;
use Base\Validator\Constraints\FileMimeType;
use Base\Validator\Constraints\FileSize;
use InvalidArgumentException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FileUploadError;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    public function __construct(BaseService $baseService, CsrfTokenManagerInterface $csrfTokenManager, ValidatorInterface $validator)
    {
        $this->baseService = $baseService;
        $this->translator  = $baseService->getTwigExtension();
        $this->csrfTokenManager = $csrfTokenManager;
        $this->validator = $validator;
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
            "data_mapping" => null,
        ]);

        $resolver->setAllowedTypes("dropzone", ['null', 'array']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $allowDelete = $options["allow_delete"];
        $isDropzone  = $options["dropzone"];
        $multiple    = $options["multiple"];

        $builder->add('file', HiddenType::class);
        $entity = $builder->getData();

        $maxFilesize   = Uploader::getMaxFilesize($options["data_class"] ?? $entity ?? null, $options["data_mapping"] ?? $builder->getName());
        if(array_key_exists('max_filesize', $options) && $options["max_filesize"])
            $maxFilesize = min($maxFilesize, $options["max_filesize"]);

        if(!$isDropzone || !$multiple)
            $builder->add('raw', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                "multiple" => $multiple,
                "constraints" => [
                    new FileSize(["max" => $maxFilesize]),
                    new FileMimeType(["type" => $options["mime_types"]])
                ]
        ]);

        if($allowDelete)
            $builder->add('delete', CheckboxType::class, ['required' => false]);

        // Process the uploaded file on submission
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
        $parent = $form->getParent();
        $entity = $parent->getData();

        if(!is_object($entity)) $files = $form->getData();
        else {
        
            $file = Uploader::getFile($entity, $form->getName());
            $files = ($file ? $file->getPath() : null);

            $propertyType = Uploader::getTypeOfField($entity, $form->getName());
            if($options["multiple"] && $propertyType != "array")
                $view->vars['max_files']     = 1;
        }

        if(!is_array($files)) $files = ($files ? [$files] : []);
        $view->vars['files'] = $files;

        $view->vars['max_files'] = $view->vars['max_files'] ?? $options["max_files"];
        $view->vars['max_filesize'] = Uploader::getMaxFilesize($options["data_class"] ?? $entity ?? null, $options["data_mapping"] ?? $form->getName());
        if(array_key_exists('max_filesize', $options))
            $view->vars['max_filesize'] = min($view->vars['max_filesize'], $options["max_filesize"]);

            
        $acceptedFiles = ($options["mime_types"] ? $options["mime_types"] : []);
        if(!$acceptedFiles && $entity) $acceptedFiles = Uploader::getMimeTypes($options["data_class"] ?? $entity, $form->getName());
        $view->vars["accept"] = $acceptedFiles;

        $view->vars["value"] = (!is_callable($options["empty_data"]) ? $options["empty_data"] : null) ?? null;
        if(($options["data_class"] ?? false) || is_object($entity))
            $view->vars['value'] = Uploader::getPublicPath($options["data_class"] ?? $entity ?? null, $options["data_mapping"] ?? $form->getName());

        if(is_array($view->vars['value']))
            $view->vars["value"] = implode("|", $view->vars["value"]);

        $view->vars["sortable"]     = null;
        $view->vars['dropzone']     = null;
        $view->vars["ajax"]         = null;
        $view->vars['multiple']     = $options['multiple'];
        $view->vars['allow_delete'] = $options['allow_delete'];

        if(is_array($options["dropzone"]) && $options["multiple"]) {

            if($options["dropzone-js"]) $this->baseService->addHtmlContent("javascripts", $options["dropzone-js"]);
            if($options["dropzone-css"]) $this->baseService->addHtmlContent("stylesheets", $options["dropzone-css"]);

            $action = (!empty($options["action"]) ? $options["action"] : ".");
            $view->vars["attr"]["class"] = "dropzone";

            $options["dropzone"] = $options["dropzone"];
            if(!array_key_exists("url", $options["dropzone"])) $options["dropzone"]["url"] = $action;
            if($options['allow_delete'] !== null) $options["dropzone"]["addRemoveLinks"] = $options['allow_delete'];
            if($options['max_filesize'] !== null) $options["dropzone"]["maxFilesize"]    = $options["max_filesize"];
            if($options['max_files']    !== null) $options["dropzone"]["maxFiles"]       = $options["max_files"];
            if($acceptedFiles) $options["dropzone"]["acceptedFiles"]  = $acceptedFiles;

            $options["dropzone"]["dictDefaultMessage"] = $options["dropzone"]["dictDefaultMessage"]
                ?? '<h4>'.$this->translator->trans2("messages.dropzone.title").'</h4><p>'.$this->translator->trans2("messages.dropzone.description").'</p>';

            if(array_key_exists("maxFiles", $options["dropzone"]) && !empty($view->vars["value"]))
                $options["dropzone"]["maxFiles"] -= count(explode("|", $view->vars["value"]));

            $token = $this->csrfTokenManager->getToken("dropzone")->getValue();
            $view->vars["ajax"]     = $this->baseService->getAsset("ux/dropzone/" . $token);
            $options["dropzone"]["url"] = $view->vars["ajax"];
            
            $view->vars["dropzone"] = json_encode($options["dropzone"]);
            $view->vars["sortable"] = json_encode($options["sortable"]);
            if($options["sortable"] && $options["sortable-js"])
                $this->baseService->addHtmlContent("javascripts", $options["sortable-js"]);
        }

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-file.js");
    }

    public function mapDataToForms($viewData, \Traversable $forms): void
    {
        // there is no data yet, so nothing to prepopulate
        if (null === $viewData) {
            return;
        }

        $fileForm = current(iterator_to_array($forms));
        if(is_array($viewData)) $viewData = array_map("basename", $viewData);
        else $viewData = basename($viewData);

        $fileForm->setData($viewData);
    }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        $children = iterator_to_array($forms);

        $rawData  = $children['raw']->getData() ?? null;
        $processedData = $children['file']->getData() ?? null;

        $viewData = ($rawData ? $rawData : null) ?? ($processedData ? $processedData : null) ?? null;
    }
}
