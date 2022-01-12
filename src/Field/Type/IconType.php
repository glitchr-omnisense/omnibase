<?php

namespace Base\Field\Type;

use Base\Model\Icon\FontAwesome;
use Base\Model\SelectInterface;

use Symfony\Component\OptionsResolver\OptionsResolver;

//https://codepen.io/peiche/pen/mRBGmR
//https://raw.githubusercontent.com/FortAwesome/Font-Awesome/master/metadata/icons.yml

class IconType extends SelectType implements SelectInterface
{
    /**
     * @var FontAwesome
     */
    protected static $instance;

    public function configureOptions(OptionsResolver $resolver)
    {
        self::$instance = new FontAwesome($this->baseService->getParameterBag("base.vendor.font_awesome.metadata"));
        self::$instance->load();

        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'fontawesome-js'    => $this->baseService->getParameterBag("base.vendor.font_awesome.js"),
            'fontawesome-css'   => $this->baseService->getParameterBag("base.vendor.font_awesome.css"),

            "autocomplete" => true,
            "autocomplete_endpoint" => "autocomplete/fa/500"
        ]);
    }

    public static function getIds(): array { return array_keys(self::$instance->getIcons()); }
    public static function getIcon(string $id, int $index = -1): ?string { return $id;  }
    public static function getText(string $id): ?string { return self::$instance->getLabel($id); }
    public static function getHtml(string $id): ?string { return null; }
    public static function getData(string $id): ?array  { return null; }
}
