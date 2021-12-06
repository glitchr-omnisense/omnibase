<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Database\Types\EnumType;
use Base\Database\Types\SetType;
use Base\Model\AutocompleteInterface;
use Base\Model\IconizeInterface;
use Base\Service\BaseService;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Exception;
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
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Traversable;

class SelectType extends AbstractType implements DataMapperInterface
{
    /** @var ClassMetadataManipulator */
    protected $classMetadataManipulator;

    /** @var BaseService */
    protected $baseService;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator, CsrfTokenManagerInterface $csrfTokenManager, BaseService $baseService)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->baseService = $baseService;

        $this->hashIds = new Hashids();
    }

    public function getBlockPrefix(): string { return 'select2'; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => null,

            'choice_filters' => null,
            'choice_icons'   => null,

            'choice_value'  => function($key)              { return $key;   },   // Return key code
            'choice_label'  => function($key, $label, $id) { return $label; },   // Return translated label
            'choice_loader' => function (Options $options) {

                return ChoiceList::loader($this, new IntlCallbackChoiceLoader(function () use ($options) {

                    $choices = $options["choices"];
                    if(!array_is_associative($choices)) {

                        $idx = array_map("strval", $choices);
                        $choices = array_replace_keys($choices, array_keys($choices), $idx);
                    }

                    return $choices;

                }), $options);
            },

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
            'closeOnSelect'      => false,
            'selectOnClose'      => false,
            'minimumResultsForSearch' => -1,

            // Autocomplete 
            'autocomplete'          => false,
            'autocomplete-endpoint' => "autocomplete",
            'autocomplete-fields'   => [],
            'autocomplete-delay'    => 500,
            'autocomplete-type'     => $this->baseService->isDebug() ? "GET" : "POST",

            // Sortable option
            'sortable'              => false
        ]);

        $resolver->setNormalizer('class', function (Options $options, $value) {
            if(!$this->classMetadataManipulator->isEntity($value)) return null;
            return $value;
        });

        $resolver->setNormalizer('autocomplete', function (Options $options, $value) {
            if($this->classMetadataManipulator->isEntity($value)) return $value;
            return null;
        });
    }
    

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDataMapper($this);

        $options["multiple"] = $this->isMultiple($builder, $options);
        if($options["select2"] === null)
            $builder->add("choice", ChoiceType::class, $options);
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
    
    protected string $dataClass;
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if($options["select2"] !== null) {

            $options["multiple"] = $this->isMultiple($form, $options);

            // Determine the returned class
            $this->dataClass = $options["class"];
            if(!$this->dataClass) {

                $data = $form->getData();
                if($data instanceof PersistentCollection) $this->dataClass = $data->getTypeClass()->getName();
                else $this->dataClass = get_class($data);
            }

            if(!$this->dataClass)
                $this->dataClass = $this->classMetadataManipulator->getDataClass($form);

            //
            // Prepare variables
            $array = [
                "class" => $this->dataClass, 
                "fields" => $options["autocomplete-fields"],
                "token" => $this->csrfTokenManager->getToken("select2")->getValue()
            ];

            $hash = $this->encode($array);

            //
            // Prepare select2 options
            $selectOpts = $options["select2"];
            $selectOpts["multiple"] = $options["multiple"] ? "multiple" : "";
            $selectOpts["ajax"] = [
                "url" => $this->baseService->getAsset($options["autocomplete-endpoint"])."/".$hash,
                "type" => $options["autocomplete-type"],
                "delay" => $options["autocomplete-delay"],
                "data" => "function (args) { return {term: args.term, page: args.page || 1}; }",
                "dataType" => "json",
                "cache" => true,
            ];
            
            if(!array_key_exists("minimumResultsForSearch", $selectOpts))
                     $selectOpts["minimumResultsForSearch"] = $options["minimumResultsForSearch"];
            if(!array_key_exists("closeOnSelect", $selectOpts))
                     $selectOpts["closeOnSelect"] = $options["closeOnSelect"];
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

            if($selectOpts["theme"] != "default" && $selectOpts["theme"] != "classic") {

                $themeCssFile = dirname($options["select2-css"]) . "/themes/select2-" . $selectOpts["theme"] . ".css";
                if(preg_match("/.*\/select2-(.*).css/", $selectOpts["theme"], $themeArray)) {

                    $selectOpts["theme"] = $themeArray[1];
                    $themeCssFile = $themeArray[0];
                }

                $this->baseService->addHtmlContent("stylesheets", $themeCssFile);
            }

            //
            // Format preselected values
            if (!$options["multiple"]) $view->vars["formattedValue"] = self::getFormattedValues($form->getData(), $options["class"]);
            else {

                $view->vars["formattedValue"] = [];
                foreach($form->getData() as $data)
                    $view->vars["formattedValue"][] = self::getFormattedValues($data, $options["class"]);
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

    public function mapDataToForms($viewData, Traversable $forms)
    {
        dump("HEHO");
    }

    public function mapFormsToData(Traversable $forms, &$viewData)
    {
        dump("HEHO");
    }

    public static function getFormattedValues($entry, $class = null) 
    {
        if ($class instanceof EnumType || $class instanceof SetType) {

            $id = $entry;
            $html = null;
            $text = "text";
            $icons = "icons";

        } else if($class !== null) {

            $accessor = PropertyAccess::createPropertyAccessor();
            $id = $accessor->isReadable($entry, "id") ? $accessor->getValue($entry, "id") : null;
    
            $html = null;
            if(class_implements_interface($entry, AutocompleteInterface::class))
                $html = $entry->autocomplete() ?? null;
            
            $text = stringeable($entry) ? strval($entry) : class_basename(get_class($entry)) + "#".$entry->getId();
            $icons = class_implements_interface($entry, IconizeInterface::class) ? $entry->__iconize() : [];

        } else {
            
            $id = $entry;
            $icons = [];
            $text = $entry;
            $html = null;
        }

        return [
            "id"   => $id ?? null,
            "icon" => begin($icons),
            "text" => $text,
            "html" => $html
        ];

    }

    protected bool $multiple;
    public function isMultiple(FormInterface|FormBuilderInterface $form, $options)
    {
        if($options["multiple"] === null && $options["class"]) {

            $target = $options["class"];
            $entityField = $form->getName();
            $entity = $this->classMetadataManipulator->getAssociationTargetClassInversedBy($target, $entityField);            

            return $this->classMetadataManipulator->isToManySide($entity, $entityField);
        }

        return $option["multiple"] ?? false;
    }
}
