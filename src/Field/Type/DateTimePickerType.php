<?php

namespace Base\Field\Type;

use Base\Service\LocaleProviderInterface;
use Base\Service\ParameterBagInterface;
use Base\Twig\Environment;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class DateTimePickerType extends AbstractType
{

    /** @var ParameterBagInterface */
    protected $parameterBag;

    /** @var LocaleProvider */
    protected $localeProvider;

    /** @var Environment */
    protected $twig;

    public function __construct(ParameterBagInterface $parameterBag, Environment $twig, LocaleProviderInterface $localeProvider)
    {
        $this->parameterBag = $parameterBag;
        $this->localeProvider = $localeProvider;
        $this->twig = $twig;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([

            // PHP Datetime format:
            // This format is replacing the shitty HTML5_FORMAT :-)
            "format" => "yyyy-MM-dd HH:mm",
            "html5"  => false,
            "widget" => "single_text",
            "required" => false,
            'use_advanced_form' => true,

            "debug" => false,
            "datetimepicker" => [
                "enableTime" => true,
                "locale" => $this->localeProvider->getLang(),
                "dateFormat" => "Y-m-d H:i", // Format must match... between format option and dateFormat (JS Format)
            ]
        ]);
    }

    public function getParent()     : ?string { return DateTimeType::class; }
    public function getBlockPrefix():  string { return 'datetimepicker'; }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        //
        // Datetime picker Options
        $dateTimePickerOpts = $options["datetimepicker"];

        $view->vars["datetimepicker"] = json_encode($dateTimePickerOpts);
    }
}
