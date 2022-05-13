<?php

namespace Base\Field\Type;

use Base\Annotations\Annotation\Uploader;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Form\FormFactory;
use Base\Service\BaseService;
use Base\Service\FileService;
use Base\Service\ImageService;
use Base\Validator\Constraints\File;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class FileType extends AbstractType implements DataMapperInterface
{
    protected $baseService;
    protected $translator;

    public function __construct(BaseService $baseService, ClassMetadataManipulator $classMetadataManipulator, CsrfTokenManagerInterface $csrfTokenManager, FormFactory $formFactory, ImageService $imageService)
    {
        $this->baseService              = $baseService;
        $this->classMetadataManipulator = $classMetadataManipulator;

        $this->translator       = $baseService->getTranslator();
        $this->csrfTokenManager = $csrfTokenManager;
        $this->formFactory      = $formFactory;
        
        $this->imageService     = $imageService;
        $this->fileService      = cast($imageService, FileService::class);
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
            'clipboard'    => false, 

            'href'         => null,
            'title'        => null,
            'allow_url'    => false,

            'sortable'     => null,
            'sortable-js'  => $this->baseService->getParameterBag("base.vendor.sortablejs.javascript"),

            'lightbox'     => ['resizeDuration' => 500, 'fadeDuration' => 250, 'imageFadeDuration' => 100],
            'lightbox-css' => $this->baseService->getParameterBag("base.vendor.lightbox.stylesheet"),
            'lightbox-js'  => $this->baseService->getParameterBag("base.vendor.lightbox.javascript"),
            'lightbox2b-js'  => $this->baseService->getParameterBag("base.vendor.lightbox2b.javascript"),

            'thumbnail_width'  => null,
            'thumbnail_height' => 120,
            'max_size'        => null,
            'max_files'       => null,
            'mime_types'      => [],
            "data_mapping"    => null,
        ]);

        $resolver->setNormalizer('class', function (Options $options, $value) {
            return $value === null ? $options["data_class"] : $value;
        });

        $resolver->setNormalizer('clipboard', function (Options $options, $value) {

            if($value) return !$options["multiple"] || $options["dropzone"] !== null;
            return false;
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

            if($options["title"] !== null)
                $form->add("title", TextType::class, $options["title"]);
            if($options["allow_url"])
                $form->add("url", UrlType::class);

            $mimeTypes   = $options["mime_types"] ?? Uploader::getMimeTypes($options["class"] ?? $entity ?? null, $options["data_mapping"] ?? $form->getName()) ;
            $maxFilesize = Uploader::getMaxFilesize($options["class"] ?? $entity ?? null, $options["data_mapping"] ?? $form->getName());
            if(array_key_exists('max_size', $options) && $options["max_size"])
                $maxFilesize = min($maxFilesize, $options["max_size"]);

            if(!$options["dropzone"]) {
         
                $form->add('raw', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                        "required"    => $options["required"] && ($data === null) && ($options["cropper"] ?? null) === null,
                        "multiple"    => $options["multiple"],
                        "constraints" => [new File(["max_size" => $maxFilesize, "mime_types" => $mimeTypes])]
                ]);
            }

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
            $this->baseService->addHtmlContent("javascripts", $options["lightbox2b-js"]);
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
        $view->vars['max_size'] = Uploader::getMaxFilesize($options["class"] ?? $entity ?? null, $options["data_mapping"] ?? $form->getName());
        if($options["max_size"] !== null)
            $view->vars['max_size'] = min($view->vars['max_size'], $options["max_size"]);
        
        $mimeTypes = $options["mime_types"];
        if(!$mimeTypes && $entity)
            $mimeTypes = Uploader::getMimeTypes($options["class"] ?? $entity, $options["data_mapping"] ?? $form->getName());

        $view->vars["mime_types"] = $mimeTypes;
        $view->vars["value"]  = (!is_callable($options["empty_data"]) ? $options["empty_data"] : null) ?? null;
        $view->vars['value']  = Uploader::getPublic($entity ?? null, $options["data_mapping"] ?? $form->getName()) ?? $files;
        
        $view->vars['pathLink'] = [];
        if(is_array($view->vars['value'])) {
            $view->vars['pathLink'] = json_encode(array_transforms(fn($k,$v):array => [basename($v), $v], $view->vars['value']));
            $view->vars["value"] = implode("|", $view->vars["value"]);
        }

        $view->vars["clipboard"]    = $options["clipboard"];
        $view->vars["sortable"]     = true;
        $view->vars['dropzone']     = null;
        $view->vars["ajax"]         = null;
        $view->vars['multiple']     = $options['multiple'];
        $view->vars['allow_delete'] = $options['allow_delete'];
        $view->vars['href']         = $options["href"];

        if(is_array($options["dropzone"]) && $options["multiple"]) {

            if($options["dropzone-js"] ) $this->baseService->addHtmlContent("javascripts:head", $options["dropzone-js"]);
            if($options["dropzone-css"]) $this->baseService->addHtmlContent("stylesheets:head", $options["dropzone-css"]);

            $action = (!empty($options["action"]) ? $options["action"] : ".");
            $view->vars["attr"]["class"] = "dropzone";

            $options["dropzone"] = $options["dropzone"];
            if(!array_key_exists("url", $options["dropzone"])) $options["dropzone"]["url"] = $action;
            if($options['allow_delete'] !== null) $options["dropzone"]["addRemoveLinks"] = $options['allow_delete'];
            if($options['max_size'] !== null) $options["dropzone"]["maxFilesize"]    = $options["max_size"];
            if($options['max_files']    !== null) $options["dropzone"]["maxFiles"]       = $options["max_files"];
            if($mimeTypes) $options["dropzone"]["mimeTypes"]  = implode(",", $mimeTypes);

            $options["dropzone"]["thumbnail_width"]  = $options['thumbnail_width'] ?? null;
            $options["dropzone"]["thumbnail_height"] = $options['thumbnail_height'] ?? null;

            $options["dropzone"]["dictDefaultMessage"] = $options["dropzone"]["dictDefaultMessage"]
                ?? '<h4>'.$this->translator->trans("@fields.fileupload.dropzone.title").'</h4><p>'.$this->translator->trans("@fields.fileupload.dropzone.description").'</p>';

            if(array_key_exists("maxFiles", $options["dropzone"]) && !empty($view->vars["value"]))
                $options["dropzone"]["maxFiles"] -= count(explode("|", $view->vars["value"]));

            $token = $this->csrfTokenManager->getToken("dropzone")->getValue();
            $view->vars["ajax"]     = $this->baseService->getAsset("ux/dropzone/" . $token);
            $options["dropzone"]["url"] = $view->vars["ajax"];
            
            $view->vars["dropzone"]  = json_encode($options["dropzone"]);

            $view->vars["sortable"]  = json_encode($options["sortable"]);
            if($options["sortable"] && $options["sortable-js"])
            $this->baseService->addHtmlContent("javascripts:head", $options["sortable-js"]);
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
        $isMultiple = $fileForm->getConfig()->getOption("multiple");

        if($isMultiple) {

            if(!is_array($viewData) && !$viewData instanceof Collection)
                $viewData = [$viewData];

        } else {

            if(is_array($viewData) || $viewData instanceof Collection)
                $viewData = first($viewData);
        }

        if(is_array($viewData)) $viewData = array_map("basename", $viewData);
        else if($viewData instanceof Collection) $viewData = $viewData->map(function($f) { return basename($f); });
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
