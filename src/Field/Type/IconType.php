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
            "class"   => null,
            "adapter"   => $this->baseService->getParameterBag("base.icon_provider.default_adapter"),

            "autocomplete" => true,
            "autocomplete_endpoint" => null,
            "autocomplete_pagesize" => 500
        ]);

        $resolver->setNormalizer('autocomplete_endpoint', function (Options $options, $value) {
            return $value ?? "autocomplete/".$options["adapter"]::getName()."/".$options["autocomplete_pagesize"];
        });
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $adapter = self::$iconProvider->getAdapter($options["adapter"]);
        foreach($adapter->getAssets() as $asset) {

            $relationship = pathinfo_relationship($asset);
            $location = $relationship == "javascript" ? "javascripts" : "stylesheets";
            $this->baseService->addHtmlContent($location, $asset);
        }
    }

    public static function getIcon(string $id, int $index = -1): ?string
    {
        $adapter = self::$iconProvider->getAdapter($id);
        return $adapter ? $id : null;
    }

    public static function getText(string $id): ?string
    {
        $adapter = self::$iconProvider->getAdapter($id);
        if($adapter) {

            $choices = $adapter->getChoices();
            if( ($choicePath = array_search_recursive($id, $choices)) )
                return $choicePath[count($choicePath)-1]; // Last but one is expected to contain "text" information
        }

        return null;
    }

    public static function getHtml(string $id): ?string { return null; }
    public static function getData(string $id): ?array { return []; }
}
