<?php

namespace Base\Field\Type;

use Base\Entity\Thread;
use Base\Field\Traits\SelectTypeInterface;
use Base\Field\Traits\SelectTypeTrait;
use Base\Service\BaseService;
use Symfony\Component\Config\Definition\Exception\Exception;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Json\Json;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

//https://codepen.io/peiche/pen/mRBGmR
//https://raw.githubusercontent.com/FortAwesome/Font-Awesome/master/metadata/icons.yml

class FontAwesomeType extends AbstractType implements SelectTypeInterface
{
    use SelectTypeTrait;

    protected static $metadata;

    /** @var BaseService */
    protected $baseService;
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        // Import select2
        $this->baseService->addHtmlContent("javascripts", $options["select2-js"]);
        $this->baseService->addHtmlContent("stylesheets", $options["select2-css"]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        if (empty(self::$metadata))
            self::$metadata = $this->baseService->getParameterBag("base.vendor.font_awesome.metadata");

        $resolver->setDefaults([
            'fontawesome-js'    => $this->baseService->getParameterBag("base.vendor.font_awesome.js"),
            'fontawesome-css'   => $this->baseService->getParameterBag("base.vendor.font_awesome.css"),

            'choices' => self::getChoices(),
            'choice_icons' => self::getIcons(),
            'choice_attr' => function (?string $entry) {
                return $entry ? ['data-icon' => "fa-fw " . $entry] : [];
            },

            'invalid_message' => function (Options $options, $previousValue) {
                    return 'Please select a icon.';
            }
        ]);
    }

    public static function getChoices(): array
    {
        $choices = [];
        foreach(self::getIcons() as $key => $icon)
        {
            $label  = $icon["label"];
            $styles = $icon["styles"];

            foreach ($styles as $style)
                $choices[ucfirst($style)." Style"][$label] = "fa".$style[0]." fa-".$key;
        }

        return $choices;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return SelectType::class;
    }

    /*
     * Available icons
     */
    protected static $icons = [];
    public static function getIcon(string $value = null): string
    {
        return self::$icons[$value] ?? "";
    }
    public static function getIcons(): array
    {
        // Default metadata location
        if (empty(self::$metadata))
            self::$metadata = dirname(__DIR__,2)."/Resources/public/vendor/font-awesome/5.15.1/metadata/icons.json";

        if (empty(self::$icons)) {

            self::$icons =
                (str_ends_with(self::$metadata, "yml") ?
                    Yaml::parse(file_get_contents(self::$metadata)) :
                (str_ends_with(self::$metadata, "yaml") ?
                    Yaml::parse(file_get_contents(self::$metadata)) :
                (str_ends_with(self::$metadata, "json") ?
                    json_decode(file_get_contents(self::$metadata), true) : [])));
        }

        return self::$icons;
    }

    protected static $version;
    public static function getVersion()
    {
        if( !empty(self::$version) )
            return self::$version;

        if ( !preg_match('/.*\/([0-9.]*)\/metadata/', self::$metadata ?? "", $match) )
            return "unk.";

        self::$version = $match[1];
        return self::$version;
    }

    public static function getValue(string $name)
    {
        if (!array_key_exists($name, self::$icons)) return "";
        return $name;
    }

    public static function getValues()
    {
        return array_keys(self::$icons);
    }

    public static function getLabel(string $name = null)
    {
        if (!$name)
            return array_map(function($icon) { return $icon["label"]; }, self::$icons);

        if (!array_key_exists($name, self::$icons)) return "";
        return self::$icons[$name]["label"];
    }

    public static function getStyles(string $name = null)
    {
        if (!$name)
            return array_map(function($icon) { return $icon["styles"]; }, self::$icons);

        if (!array_key_exists($name, self::$icons)) return [];
        return self::$icons[$name]["styles"] . " " ;
    }

    public static function getUnicode(string $name = null)
    {
        if (!$name)
            return array_map(function($icon) { return $icon["unicode"]; }, self::$icons);

        if (!array_key_exists($name, self::$icons)) return [];
        return self::$icons[$name]["unicode"];
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'icons';
    }
}
