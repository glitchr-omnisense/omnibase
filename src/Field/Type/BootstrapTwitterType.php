<?php

namespace Base\Field\Type;

use Base\Model\Icon\FontAwesome;
use Base\Model\SelectInterface;

use Symfony\Component\OptionsResolver\OptionsResolver;

//https://codepen.io/peiche/pen/mRBGmR
//https://raw.githubusercontent.com/FortAwesome/Font-Awesome/master/metadata/icons.yml

class BootstrapTwitterType extends SelectType implements SelectInterface
{
    /**
     * @var BootstrapTwitter
     */
    protected static $instance;

    public function configureOptions(OptionsResolver $resolver)
    {
        self::$instance = new FontAwesome($this->baseService->getParameterBag("base.vendor.bootstrap_twitter.metadata"));
        self::$instance->load();

        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'bootstrap-twitter-css'   => $this->baseService->getParameterBag("base.vendor.bootstrap_twitter.css"),

            "autocomplete" => true,
            "autocomplete_endpoint" => "autocomplete/bi/500"
        ]);
    }

    public static function getIds(): array { return array_keys(self::$instance->getIcons()); }
    public static function getIcon(string $id, int $index = -1): ?string { return $id;  }
    public static function getText(string $id): ?string { return self::$instance->getLabel($id); }
    public static function getHtml(string $id): ?string { return null; }
    public static function getData(string $id): ?array  { return null; }
}
