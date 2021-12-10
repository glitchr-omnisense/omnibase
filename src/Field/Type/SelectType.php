<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Database\Types\EnumType;
use Base\Database\Types\SetType;
use Base\Model\AutocompleteInterface;
use Base\Model\IconizeInterface;
use Base\Service\BaseService;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;

use Hashids\Hashids;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\IntlCallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Traversable;

class SelectType extends AbstractType implements DataMapperInterface
{
    /** @var ClassMetadataManipulator */
    protected $classMetadataManipulator;

    /** @var BaseService */
    protected $baseService;

    public function __construct(EntityManagerInterface $entityManager, TranslatorInterface $translator, ClassMetadataManipulator $classMetadataManipulator, CsrfTokenManagerInterface $csrfTokenManager, BaseService $baseService)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->baseService = $baseService;
        $this->translator = $translator;
        $this->entityManager = $entityManager;
        $this->hashIds = new Hashids();
    }

    public function getBlockPrefix(): string { return 'select2'; }

    public function guessClass($form, $options, $data) :?string {

        $class = $options["class"] ?? null;
        if(!$class) {
    
            if($data instanceof PersistentCollection) $class = $data->getTypeClass()->getName();
            else $class = is_object($data) ? get_class($data) : null;
        }
        
        if(!$class)
            $class = $this->classMetadataManipulator->getDataClass($form);

        return $class;
    }

    public function guessChoices($options)
    {
        if ($options["choices"] !== null && $this->classMetadataManipulator->isEnumType($options["class"]) ||
                                            $this->classMetadataManipulator->isSetType ($options["class"])) {

            return $options["class"]::getPermittedValues();
        }
        return $options["choices"] ?? null;
    }

    public function guessIfMultiple(FormInterface|FormBuilderInterface $form, $options)
    {
        if($options["multiple"] === null && $options["class"]) {
            
            $target = $options["class"];
            $entityField = $form->getName();

            if($this->classMetadataManipulator->isEntity($target)) {

                $entity = $this->classMetadataManipulator->getTargetClass($target, $entityField);
                return $this->classMetadataManipulator->isToManySide($entity, $entityField);

            } else if($this->classMetadataManipulator->isEnumType($target)) {

                return false;

            } else if($this->classMetadataManipulator->isSetType($target)) {

                return true;
            }
        }

        return $option["multiple"] ?? false;
    }

    public function guessAutocomplete($options)
    {
        if($options["choices"]) return false;
        if($options["autocomplete"] === null && $options["class"]) {
            
            $target = $options["class"];
            if($this->classMetadataManipulator->isEntity($target))
                return true;
            if($this->classMetadataManipulator->isEnumType($target))
                return false;
            if($this->classMetadataManipulator->isSetType($target))
                return false;
        }

        return $option["autocomplete"] ?? false;
    }

    public function guessChoiceFilters($options, $data)
    {
        if ($options["choice_filters"] === null) {
            
            $options["choice_filters"] = [];
            if(is_array($data)) {
                foreach($data as $entry)
                    if(is_object($entry)) $options["choice_filters"][] = get_class($entry);
            } else {
                if(is_object($data)) $options["choice_filters"][] = get_class($data);
            }

            if(!$options["choice_filters"]  && $options["class"]) {
                $options["choice_filters"][] = $options["class"];
            }
        }

        return $option["choice_filters"] ?? [];
    }

    public function encode(array $array) : string
    {
        $hex = bin2hex(serialize($array));
        return $this->hashIds->encodeHex($hex);
    }

    public function decode(string $hash): array
    {
        $hex = $this->hashIds->decodeHex($hash);
        return unserialize(hex2bin($hex));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => null,

            //'query_builder'   => null,

            'choices' => null,
            'choice_filters' => null,
            // 'choice_value'   => function($key)              { return $key;   },   // Return key code
            // 'choice_label'   => function($key, $label, $id) { return $label; },   // Return translated label
            // 'choice_loader'  => function (Options $options) {

            //     return ChoiceList::loader($this, new IntlCallbackChoiceLoader(function () use ($options) {

            //         $choices = $options["choices"];
            //         if(!is_associative($choices)) {

            //             $idx = array_map("strval", $choices);
            //             $choices = array_replace_keys($choices, array_keys($choices), $idx);
            //         }

            //         return $choices;

            //     }), $options);
            // },

            'select2'          => [],
            'select2-js'       => $this->baseService->getParameterBag("base.vendor.select2.js"),
            'select2-css'      => $this->baseService->getParameterBag("base.vendor.select2.css"),
            'theme'            => $this->baseService->getParameterBag("base.vendor.select2.theme"),

            // Use 'template' in replacement of selection/result template
            'template'          => "function(option, that) { return $('<span class=\"select2-selection__entry\">' + (option.html ? option.html : (option.icon ? '<i class=\"'+option.icon+'\"></i>  ' : '') + option.text + '</span>')); }",
            'templateSelection' => null,
            'templateResult'    => null,

            // Generic parameters
            'placeholder'        => "Choose your selection..",
            'required'           => true,
            'multiple'           => null,
            'maximum'            => 0,
            'tags'               => false,
            'minimumInputLength' => 0,
            'tokenSeparators'    => [' ', ',', ';'],
            'closeOnSelect'      => null,
            'selectOnClose'      => false,
            'minimumResultsForSearch' => 0,

            // Autocomplete 
            'autocomplete'          => null,
            'autocomplete_endpoint' => "autocomplete",
            'autocomplete_fields'   => [],
            'autocomplete_delay'    => 500,
            'autocomplete_type'     => $this->baseService->isDebug() ? "GET" : "POST",

            // Sortable option
            'sortable'              => true
        ]);

        $resolver->setNormalizer('class', function (Options $options, $value) {
            if(!$this->classMetadataManipulator->isEntity($value)) return null;
            return $value;
        });
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {

            $form = $event->getForm();
            $data = $event->getData();

            /* Override options.. I couldn't done that without accessing data */
            // It might be good to get read of that and be able to use normalizer.. as expected
            $options["class"]          = $this->guessClass($form, $options, $data);
            $options["multiple"]       = $this->guessIfMultiple($form, $options);
            $options["autocomplete"]   = $this->guessAutocomplete($options);
            $options["choice_filters"] = $this->guessChoiceFilters($options, $data);
            $options["choices"]        = $this->guessChoices($options);

            $multipleExpected = $data instanceof Collection || is_array($data);
            if($options["multiple"] && !$multipleExpected) 
                throw new \Exception("Data is not a collection in \"".$form->getName()."\" field and you required the option \"multiple\".. Please set multiple to \"false\"");

            $formOptions = [
                'choices'    => [],
                'multiple'   => $options["multiple"]
            ];

            $form->add('choice', ChoiceType::class, $formOptions);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$options) {
            
            $form = $event->getForm();
            $data = $event->getData();
            
            $choiceData = $options["multiple"] ? $data["choice"] : [$data["choice"]];

            if ($options["class"]) {

                $dataset = $form->getData() instanceof Collection ? $form->getData()->toArray() : ( !is_array($form->getData()) ? [$form->getData()] : $form->getData() );
                $formattedData = array_key_transforms(function ($key, $value, $i, $callback) use (&$options) : array { 

                    // Recursive categories
                    if(is_array($value) && is_associative($value))
                        return [$i, array_key_transforms($callback, $value)];

                    // Format values
                    $entry = self::getFormattedValues($value, $options["class"], $this->translator);
                    if(!$options["class"]) $entry["text"] = $key;

                    return [$entry["id"], $entry["text"]];

                }, $dataset ?? []);

                // Search missing information
                $missingData = [];
                $knownData = array_keys($formattedData);
                foreach($choiceData as $data) {
                    
                    if(!in_array($data, $knownData))
                        $missingData[] = $data;
                }

                if($this->classMetadataManipulator->isEntity($options["class"])) {

                    $classRepository = $this->entityManager->getRepository($options["class"]);
                    $missingData = $classRepository->findById($missingData)->getResult();
                }

                $formattedData += array_key_transforms(function ($key, $value, $i, $callback) use (&$options) : array { 
                    
                    // Recursive categories
                    if(is_array($value) && is_associative($value))
                        return [$i, array_key_transforms($callback, $value)];

                    // Format values
                    $entry = self::getFormattedValues($value, $options["class"], $this->translator);

                    if(!$options["class"]) $entry["text"] = $key;

                    return [$entry["id"], $entry["text"]];

                }, $missingData ?? []);

                //
                // Compute in choice list format
                $choices = array_filter(array_key_transforms(function($key, $value) use($formattedData) : ?array {

                    $id    = $value;
                    $label = $formattedData[$id] ?? null;
                    if($label === null) return null;

                    return [$label, $value];

                }, $choiceData));
            }

            //
            // Note for later: when disabling select2, it might happend that the label of the label of selected entries are wrong
            // 
            $formOptions = [
                'choices'  => $choices, 
                'multiple' => $options["multiple"]
            ];

            $form->remove('choice')->add('choice', ChoiceType::class, $formOptions);
        });
    }

    public function mapDataToForms($viewData, Traversable $forms) { }

    public function mapFormsToData(Traversable $forms, &$viewData)
    {
        $choiceType = current(iterator_to_array($forms));
        $choiceData = $choiceType->getViewData();

        $options = $choiceType->getConfig()->getOptions();
        $options["class"] = $this->guessClass($choiceType, $options, $choiceType->getData());
        if ($this->classMetadataManipulator->isEntity($options["class"])) {

            $classRepository = $this->entityManager->getRepository($options["class"]);
            if($options["multiple"]) {
                $choiceData = $classRepository->findById($choiceData)->getResult();
            } else {
                $choiceData = $classRepository->findOneById($choiceData);
            }
        }
        
        if($viewData instanceof Collection) {
        
            $viewData->clear();
            foreach($choiceData as $data)
                $viewData->add($data);

        } else if($options["multiple"]) {

            $viewData = [];
            foreach($choiceData as $data)
                $viewData[] = $data;

        } else $viewData = $choiceData;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if($options["select2"] !== null) {

            /* Override options.. I couldn't done that without accessing data */
            $options["class"]          = $this->guessClass($form, $options, $form->getData());
            $options["multiple"]       = $this->guessIfMultiple($form, $options);
            $options["autocomplete"]   = $this->guessAutocomplete($options);
            $options["choice_filters"] = $this->guessChoiceFilters($options, $form->getData());
            $options["choices"]        = $this->guessChoices($options);

            $multipleExpected = $form->getData() instanceof Collection || is_array($form->getData());
            if($options["multiple"] && !$multipleExpected) 
                throw new \Exception("Data is not a collection in \"".$form->getName()."\" field and you required the option \"multiple\".. Please set multiple to \"false\"");

            //
            // Prepare variables
            $array = [
                "class" => $options["class"], 
                "fields" => $options["autocomplete_fields"],
                "filters" => $options["choice_filters"],
                "token" => $this->csrfTokenManager->getToken("select2")->getValue()
            ];

            $hash = $this->encode($array);

            //
            // Prepare select2 options
            $selectOpts = $options["select2"];
            $selectOpts["multiple"] = $options["multiple"] ? "multiple" : "";
            if($options["autocomplete"]) {

                $selectOpts["ajax"] = [
                    "url" => $this->baseService->getAsset($options["autocomplete_endpoint"])."/".$hash,
                    "type" => $options["autocomplete_type"],
                    "delay" => $options["autocomplete_delay"],
                    "data" => "function (args) { return {term: args.term, page: args.page || 1}; }",
                    "dataType" => "json",
                    "cache" => true,
                ];
            }

            if(!array_key_exists("minimumResultsForSearch", $selectOpts))
                     $selectOpts["minimumResultsForSearch"] = $options["minimumResultsForSearch"];
            if(!array_key_exists("closeOnSelect", $selectOpts))
                     $selectOpts["closeOnSelect"] = $options["closeOnSelect"] ?? !$options["multiple"];
            if(!array_key_exists("selectOnClose", $selectOpts))
                     $selectOpts["selectOnClose"] = $options["selectOnClose"];

            if(!array_key_exists("placeholder", $selectOpts))
                     $selectOpts["placeholder"] = $options["placeholder"] ?? "";
            if(!array_key_exists("language", $selectOpts))
                     $selectOpts["language"]        = $options["locale"] ?? \Locale::getDefault();
            if(!array_key_exists("tokenSeparators", $selectOpts))
                     $selectOpts["tokenSeparators"] = $selectOpts["tokenSeparators"] ?? $options["tokenSeparators"];
            if(!array_key_exists("allowClear", $selectOpts))
                     $selectOpts["allowClear"]  = (array_key_exists("required"       , $options) && !$options["required"]   ) ? true                      : false;
            if(!array_key_exists("maximum", $selectOpts))
                     $selectOpts["maximum"]     = (array_key_exists("maximum"        , $options) &&  $options["maximum"] > 0) ? $options["maximum"]         : "";
            if(!array_key_exists("tags", $selectOpts))
                     $selectOpts["tags"]        = (array_key_exists("tags"           , $options) &&  $options["tags"]       ) ? true                      : false;

            // /!\ NB: Template functions must be defined later on because
            // the width is determined by the size of the biggeste <option> entry
            
            if(!array_key_exists("template", $selectOpts))
                     $selectOpts["template"]          = $options["template"]          ?? "";
            if(!array_key_exists("templateResult", $selectOpts))
                     $selectOpts["templateResult"]    = $options["templateResult"]    ?? $selectOpts["template"];
            if(!array_key_exists("templateSelection", $selectOpts))
                     $selectOpts["templateSelection"] = $options["templateSelection"] ?? $selectOpts["template"];

            if(!array_key_exists("theme", $selectOpts))
                     $selectOpts["theme"] = $options["theme"];

            //
            // Format preselected values
            $selectedData  = [];
            $dataset = $form->getData() instanceof Collection ? $form->getData()->toArray() : ( !is_array($form->getData()) ? [$form->getData()] : $form->getData() );
            $formattedData = array_key_transforms(function ($key, $value, $i, $callback) use ($dataset, &$options, &$selectedData) : array { 

                // Recursive categories
                if(is_array($value) && is_associative($value))
                    return [$i, ["text" => $key, "children" => array_key_transforms($callback, $value)]];

                // Format values
                $entry = self::getFormattedValues($value, $options["class"], $this->translator);
                
                // // Check if entry selected
                $entry["selected"] = false;
                foreach($dataset as $data)
                    $entry["selected"] |= ($value === $data);
            
                if($entry["selected"])
                    $selectedData[]  = $entry["id"];

                    // Special display if no class found
                if(!$options["class"]) {

                    $entry["text"] = $key;
                    $entry["icon"] = $value;
                }

                return [$i, $entry];

            }, $options["choices"] ?? $dataset ?? []);

            $selectOpts["data"]     = $formattedData;
            $selectOpts["selected"] = $selectedData;

            //
            // Set select2 theme
            if($selectOpts["theme"] != "default" && $selectOpts["theme"] != "classic") {

                $themeCssFile = dirname($options["select2-css"]) . "/themes/select2-" . $selectOpts["theme"] . ".css";
                if(preg_match("/.*\/select2-(.*).css/", $selectOpts["theme"], $themeArray)) {

                    $selectOpts["theme"] = $themeArray[1];
                    $themeCssFile = $themeArray[0];
                }

                $this->baseService->addHtmlContent("stylesheets", $themeCssFile);
            }

            //
            // Default select2 initialializer
            $view->vars["select2"] = json_encode($selectOpts);
            $view->vars["select2-sortable"] = $options["sortable"];
            
            // Import select2
            $this->baseService->addHtmlContent("javascripts", $options["select2-js"]);
            $this->baseService->addHtmlContent("stylesheets", $options["select2-css"]);
            $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-select2.js");
        }
    }
    

    public static function getFormattedValues($entry, $class = null, TranslatorInterface $translator = null) 
    {
        if (is_subclass_of($class, EnumType::class) || is_subclass_of($class, SetType::class)) {
            
            $className = class_basename($class);
            $id = $entry;
            $html = null;

            $text = ($translator ? $translator->trans(camel_to_snake($className.".".strtolower($entry).".singular"), [], "enum") : $entry);
            $icons = [$class::getIcons(0)[$entry]];

        } else if(is_object($entry) && $class !== null) {

            $accessor = PropertyAccess::createPropertyAccessor();
            $id = $accessor->isReadable($entry, "id") ? strval($accessor->getValue($entry, "id")) : null;
            $html = null;
            if(class_implements_interface($entry, AutocompleteInterface::class))
                $html = $entry->autocomplete() ?? null;
            
            $className = class_basename(get_class($entry));
            if($translator) $className = $translator->trans(camel_to_snake($className.".singular"), [], "entities");

            $text = stringeable($entry) ? strval($entry) : $className + "#".$entry->getId();

            $icons = $entry->__iconize() ?? [];
            if(empty($icons) && class_implements_interface($entry, IconizeInterface::class)) 
                $icons = $entry::__staticIconize();
            
        } else {

            $id = $entry;
            $icons = [];
            $text = $entry;
            $html = null;
        }

        return [
            "id"   => $id ?? null,
            "icon" => begin($icons),
            "text" => ucwords($text),
            "html" => $html
        ];
    }
}
