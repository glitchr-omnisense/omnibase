<?php

namespace Base\Field\Type;

use Base\Service\ParameterBagInterface;
use Base\Twig\Environment;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class DateTimePickerType extends AbstractType
{
    public function __construct(ParameterBagInterface $parameterBag, Environment $twig)
    {
        $this->parameterBag = $parameterBag;
        $this->twig = $twig;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'moment-js'    => $this->parameterBag->get("base.vendor.moment.javascript"),
            'datetimepicker-js'    => $this->parameterBag->get("base.vendor.datetimepicker.javascript"),
            'datetimepicker-css'   => $this->parameterBag->get("base.vendor.datetimepicker.stylesheet"),

            // PHP Datetime format:
            // This format is replacing the shitty HTML5_FORMAT :-)
            "format" => "yyyy-MM-dd HH:mm:ss",
            "html5"  => false,
            "widget" => "single_text",
            "required" => false,

            "datetimepicker" => [
                // "debug" => true,
                "keepOpen" => true,
                "format" => "YYYY-MM-DD HH:mm:ss", // JS Datetime Format
                "sideBySide" => true,
                "allowInputToggle" => true
            ]
        ]);
    }

    public function getParent()     : ?string { return DateTimeType::class; }
    public function getBlockPrefix():  string { return 'datetimepicker'; }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        // Import datetimepicker
        $this->twig->addHtmlContent("javascripts:head", $options["moment-js"]);
        $this->twig->addHtmlContent("javascripts:head", $options["datetimepicker-js"]);
        $this->twig->addHtmlContent("stylesheets:head", $options["datetimepicker-css"]);

        //
        // Datetime picker Options
        $dateTimePickerOpts = $options["datetimepicker"];
        $dateTimePickerOpts["defaultDate"] = $view->vars["value"];

        $view->vars["datetimepicker"] = json_encode($dateTimePickerOpts);

        //
        // Datetime picker initialializer
        $this->twig->addHtmlContent("javascripts:body", "bundles/base/form-type-datetimepicker.js");
    }
}
