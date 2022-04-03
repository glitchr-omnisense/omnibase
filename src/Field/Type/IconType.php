<?php

namespace Base\Field\Type;

use Base\Model\SelectInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

//https://codepen.io/peiche/pen/mRBGmR
//https://raw.githubusercontent.com/FortAwesome/Font-Awesome/master/metadata/icons.yml

class IconType extends SelectType implements SelectInterface
{
    public static $iconProvider = null;
    public function __construct(...$args)
    {
        parent::__construct(...$args);
        self::$iconProvider = $this->baseService->getIconProvider();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'class'   => null,
            'provider'   => $this->baseService->getParameterBag("base.icon_provider.default_provider"),

            "autocomplete" => true,
            "autocomplete_endpoint" => null,
            "autocomplete_pagesize" => 500
        ]);

        $resolver->setNormalizer('autocomplete_endpoint', function (Options $options, $value) {
            return $value ?? "autocomplete/".$options["provider"]::getName()."/".$options["autocomplete_pagesize"];
        });
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $iconProvider = self::$iconProvider->getProvider($options["provider"]);
        foreach($iconProvider->getAssets() as $asset) {

            $relationship = pathinfo_relationship($asset);
            $location = $relationship == "javascript" ? "javascripts" : "stylesheets";
            $this->baseService->addHtmlContent($location, $asset);
        }
    }

    public static function getIcon(string $id, int $index = -1): ?string
    {
        $provider = self::$iconProvider->getProvider($id);
        return $provider ? $id : null;
    }

    public static function getText(string $id): ?string
    {
        $provider = self::$iconProvider->getProvider($id);
        if($provider) {

            $choices = $provider->getChoices();
            if( ($choicePath = array_search_recursive($id, $choices)) )
                return $choicePath[count($choicePath)-1]; // Last but one is expected to contain "text" information
        }

        return null;
    }

    public static function getHtml(string $id): ?string { return null; }
    public static function getData(string $id): ?array { return []; }
}
