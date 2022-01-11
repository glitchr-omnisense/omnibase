<?php

namespace Base\Field\Type;

use Base\Entity\Thread;
use Base\Field\Traits\SelectTypeInterface;
use Base\Field\Traits\SelectTypeTrait;
use Base\Model\FontAwesome;
use Base\Model\SelectInterface;
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

class FontAwesomeType extends SelectType implements SelectInterface
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
