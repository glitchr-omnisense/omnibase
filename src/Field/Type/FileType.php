<?php

namespace Base\Field\Type;

use Base\Annotations\Annotation\Uploader;
use Base\Database\Factory\ClassMetadataManipulator;

use Base\Service\BaseService;
use Base\Validator\Constraints\FileMimeType;
use Base\Validator\Constraints\FileSize;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FileType extends AbstractType implements DataMapperInterface
{
    protected $baseService;
    protected $translator;

    public function __construct(BaseService $baseService, ClassMetadataManipulator $classMetadataManipulator, CsrfTokenManagerInterface $csrfTokenManager, ValidatorInterface $validator)
    {
        $this->baseService              = $baseService;
        $this->classMetadataManipulator = $classMetadataManipulator;

        $this->translator       = $baseService->getTranslator();
        $this->csrfTokenManager = $csrfTokenManager;
        $this->validator        = $validator;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'fileupload';
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class'        => null,
            'empty_data'   => null,

            'dropzone'     => [],
            'dropzone-js'  => $this->baseService->getParameterBag("base.vendor.dropzone.javascript"),
            'dropzone-css' => $this->baseService->getParameterBag("base.vendor.dropzone.stylesheet"),

            'allow_delete' => true,
            'multiple'     => false,
            'href' => null,

            'sortable'     => true,
            'sortable-js'  => $this->baseService->getParameterBag("base.vendor.sortablejs.javascript"),

            'lightbox'     => ['resizeDuration' => 500, 'fadeDuration' => 250, 'imageFadeDuration' => 100],
            'lightbox-js'  => $this->baseService->getParameterBag("base.vendor.lightbox.javascript"),
            'lightbox-css' => $this->baseService->getParameterBag("base.vendor.lightbox.stylesheet"),

            'thumbnailWidth'     => null,
            'thumbnailHeight'    => 120,
            'max_filesize'       => null,
            'max_files'          => null,
            'mime_types'         => null,
            "data_mapping"       => null,
        ]);

        $resolver->setNormalizer('class', function (Options $options, $value) {
            return $value === null ? $options["data_class"] : $value;
        });

        $resolver->setAllowedTypes("dropzone", ['null', 'array']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {

            $form = $event->getForm();
            $data = $event->getData();

            $form->add('file', HiddenType::class, ["required" => $options["required"]]);

            $maxFilesize   = Uploader::getMaxFilesize($options["class"] ?? $entity ?? null, $options["data_mapping"] ?? $form->getName());
            if(array_key_exists('max_filesize', $options) && $options["max_filesize"])
                $maxFilesize = min($maxFilesize, $options["max_filesize"]);

            $constraints = [new FileSize(["max" => $maxFilesize])];
            if($options["mime_types"])
                $constraints[] = new FileMimeType(["type" => $options["mime_types"]]);

            if(!$options["dropzone"] || !$options["multiple"])
                $form->add('raw', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                    "required"    => $options["required"] && ($data === null),
                    "multiple"    => $options["multiple"],
                    "constraints" => $constraints
            ]);

            if($options["allow_delete"])
                $form->add('delete', CheckboxType::class, ['required' => false]);
        });

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
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $parent = $form->getParent();
        $entity = $parent->getData();

        $view->vars["lightbox"] = null;
        if(is_array($options["lightbox"])) {

            $this->baseService->addHtmlContent("javascripts", $options["lightbox-js"]);
            $this->baseService->addHtmlContent("stylesheets", $options["lightbox-css"]);

            $view->vars["lightbox"]  = json_encode($options["lightbox"]);
        }
        
        if($this->classMetadataManipulator->isEntity($entity)) {

            $files = Uploader::get($entity, $form->getName());
            if(!is_array($files)) $files = [$files];

            $files = array_map(fn($f) => $f ? $f->getPath() : null, $files);

            $propertyType = Uploader::getTypeOfField($entity, $form->getName());
            if($options["multiple"] && $propertyType != "array")
                $view->vars['max_files']     = 1;

        } else {

            $files = $form->getData();
        }

        if(!is_array($files)) $files = $files ? [$files] : [];
        $view->vars['files'] = array_filter($files);

        $view->vars['max_files'] = $view->vars['max_files'] ?? $options["max_files"];
        $view->vars['max_filesize'] = Uploader::getMaxFilesize($options["class"] ?? $entity ?? null, $options["data_mapping"] ?? $form->getName());
        if($options["max_filesize"] !== null)
            $view->vars['max_filesize'] = min($view->vars['max_filesize'], $options["max_filesize"]);
        $acceptedFiles = $options["mime_types"] ?? [];
        if(!$acceptedFiles && $entity)
            $acceptedFiles = Uploader::getMimeTypes($options["class"] ?? $entity, $options["data_mapping"] ?? $form->getName());

        $view->vars["accept"] = $acceptedFiles;
        $view->vars["value"]  = (!is_callable($options["empty_data"]) ? $options["empty_data"] : null) ?? null;
        $view->vars['value']  = Uploader::getPublic($entity ?? null, $options["data_mapping"] ?? $form->getName()) ?? $files;
        if(is_array($view->vars['value']))
            $view->vars["value"] = implode("|", $view->vars["value"]);

        $view->vars["sortable"]     = null;
        $view->vars['dropzone']     = null;
        $view->vars["ajax"]         = null;
        $view->vars['multiple']     = $options['multiple'];
        $view->vars['allow_delete'] = $options['allow_delete'];
        $view->vars['href']         = $options["href"];

        if(is_array($options["dropzone"]) && $options["multiple"]) {

            if($options["dropzone-js"] ) $this->baseService->addHtmlContent("javascripts", $options["dropzone-js"]);
            if($options["dropzone-css"]) $this->baseService->addHtmlContent("stylesheets", $options["dropzone-css"]);

            $action = (!empty($options["action"]) ? $options["action"] : ".");
            $view->vars["attr"]["class"] = "dropzone";

            $options["dropzone"] = $options["dropzone"];
            if(!array_key_exists("url", $options["dropzone"])) $options["dropzone"]["url"] = $action;
            if($options['allow_delete'] !== null) $options["dropzone"]["addRemoveLinks"] = $options['allow_delete'];
            if($options['max_filesize'] !== null) $options["dropzone"]["maxFilesize"]    = $options["max_filesize"];
            if($options['max_files']    !== null) $options["dropzone"]["maxFiles"]       = $options["max_files"];
            if($acceptedFiles) $options["dropzone"]["acceptedFiles"]  = implode(",", $acceptedFiles);

            $options["dropzone"]["thumbnailWidth"]  = $options['thumbnailWidth'] ?? null;
            $options["dropzone"]["thumbnailHeight"] = $options['thumbnailHeight'] ?? null;

            $options["dropzone"]["dictDefaultMessage"] = $options["dropzone"]["dictDefaultMessage"]
                ?? '<h4>'.$this->translator->trans("@fields.fileupload.dropzone.title").'</h4><p>'.$this->translator->trans("@fields.fileupload.dropzone.description").'</p>';

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
        $childForm = iterator_to_array($forms);

        $rawData  = $childForm['raw']->getData() ?? null;
        $processedData = $childForm['file']->getData() ?? null;

        $viewData = ($rawData ? $rawData : null) ?? ($processedData ? $processedData : null) ?? null;
    }
}
