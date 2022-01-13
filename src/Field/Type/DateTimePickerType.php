<?php

namespace Base\Field\Type;

use Base\Service\BaseService;
use Symfony\Component\Config\Definition\Exception\Exception;

use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ArrayToPartsTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DataTransformerChain;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class DateTimePickerType extends AbstractType
{
    /** @var BaseService */
    protected $baseService;
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'moment-js'    => $this->baseService->getParameterBag("base.vendor.moment.javascript"),
            'datetimepicker-js'    => $this->baseService->getParameterBag("base.vendor.datetimepicker.javascript"),
            'datetimepicker-css'   => $this->baseService->getParameterBag("base.vendor.datetimepicker.stylesheet"),

            // PHP Datetime format:
            // This format is replacing the shitty HTML5_FORMAT :-)
            "format" => "yyyy-MM-dd HH:mm:ss",
            "html5"  => false,
            "widget" => "single_text",
            "required" => false,

            "datetimepicker" => [
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
        $this->baseService->addHtmlContent("javascripts", $options["moment-js"]);
        $this->baseService->addHtmlContent("javascripts", $options["datetimepicker-js"]);
        $this->baseService->addHtmlContent("stylesheets", $options["datetimepicker-css"]);

        //
        // Datetime picker Options
        $dateTimePickerOpts = $options["datetimepicker"];
        $dateTimePickerOpts["defaultDate"] = $view->vars["value"];

        $view->vars["datetimepicker"] = json_encode($dateTimePickerOpts);

        //
        // Datetime picker initialializer
        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-datetimepicker.js");
    }
}
