<?php

namespace Base\Field\Type;

use Base\Entity\User;
use Base\Field\Traits\SelectTypeInterface;
use Base\Field\Traits\SelectTypeTrait;
use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\ChoiceList\Loader\IntlCallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class SelectType extends AbstractType
{
    use SelectTypeTrait;

    /** @var BaseService */
    protected $baseService;
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent() : ?string
    {
        return ChoiceType::class;
    }
    public function getBlockPrefix(): string
    {
        return 'select2';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
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
            'template'          => "function(option, that) { if (option.element) { var icon = $(option.element).attr('data-icon') || ''; if(icon) icon = '<i class=\"'+icon+'\"></i> '; return $('<span>'+icon+option.text + '</span>'); } return option.text; }",
            'templateSelection' => null,
            'templateResult'    => null,

            // Generic parameters
            'placeholder'     => "",
            'required'        => true,
            'multiple'        => false,
            'maximum'         => 0,
            'tags'            => false,
            'tokenSeparators' => [' ', ',', ';'],
            'sortable' => false
        ]);

        $resolver->setNormalizer('multiple', function (Options $options, $value) {

            if($value && $options["data_class"])
                throw new \RuntimeException("Unexpected \"data_class\" option combined with \"multiple\" option  detected.. This is not allowed in \"".get_called_class()."\"");

            return $value;
        });

    }
    
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if($options["select2"] !== null) {

            // Import select2
            $this->baseService->addHtmlContent("javascripts", $options["select2-js"]);
            $this->baseService->addHtmlContent("stylesheets", $options["select2-css"]);

            // Default options
            $selectOpts = $options["select2"];
            if(!array_key_exists("placeholder", $selectOpts))
                     $selectOpts["placeholder"] = $options["placeholder"] ?? "";

            if(!array_key_exists("language", $selectOpts))
                     $selectOpts["language"]        = $options["locale"] ?? \Locale::getDefault();
            if(!array_key_exists("tokenSeparators", $selectOpts)) {
                     $selectOpts["tokenSeparators"] = $options["tokenSeparators"]    ?? [" "];
                     $selectOpts["tokenSeparators"] = "['" . implode("','", $selectOpts["tokenSeparators"]) . "']";
            }

            if(!array_key_exists("allowClear", $selectOpts))
                     $selectOpts["allowClear"]  = (array_key_exists("required"       , $options) && !$options["required"]   ) ? "true"                      : "false";
            if(!array_key_exists("multiple", $selectOpts))
                     $selectOpts["multiple"]    = (array_key_exists("multiple"       , $options) &&  $options["multiple"]   ) ? "multiple"                  : "";
            if(!array_key_exists("maximum", $selectOpts))
                     $selectOpts["maximum"]     = (array_key_exists("maximum"        , $options) &&  $options["maximum"] > 0) ? $options["maximum"]         : "";
            if(!array_key_exists("tags", $selectOpts))
                     $selectOpts["tags"]        = (array_key_exists("tags"           , $options) &&  $options["tags"]       ) ? "true"                      : "false";

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
            // Default select2 initialializer
            $view->vars["select2"] = json_encode($selectOpts);
            $view->vars["select2-sortable"] = $options["sortable"];

            $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-select2.js");
        }
    }
}
