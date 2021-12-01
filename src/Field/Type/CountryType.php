<?php

namespace Base\Field\Type;

use Base\Field\Traits\SelectTypeInterface;
use Base\Field\Traits\SelectTypeTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class CountryType extends AbstractType implements SelectTypeInterface
{
    use SelectTypeTrait;

    private static $additionalList = [];
    private static $rejectCountryList = [ // Rejected just because missing flag.. to do later
        "AO", "AI", "AQ", "AW", "BM", "CW", "GS", "GI", "GL",
        "GP", "GU", "GG", "GF", "BV", "CX", "IM", "NF", "AX",
        "KY", "CC", "CK", "FO", "HM", "FK", "MP", "UM", "PN",
        "TC", "VG", "VI", "JE", "RE", "MQ", "YT", "MS", "NU",
        "NC", "BQ", "PF", "MO", "EH", "BL", "MF", "SX", "PM",
        "SH", "AS", "SJ", "TF", "IO", "TK", "WF",
    ];

    // A way to add countries to the list.. (Another way is shown below using options)
    public static function addCountry($code, $country) { return self::addCountries([$code => $country]); }
    public static function addCountries($array) {

        $countryList = Countries::getNames();
        foreach($array as $code => $country) {

            if( array_key_exists($code, $countryList) ) throw new Exception("Country code \"$code\" ($country) already added in the true country list");
            if( array_key_exists($code, self::$additionalList) ) throw new Exception("Country code \"$code\" ($country) already added in the fake country list");

            self::$additionalList[$code] = $country;
        }
    }

    public static function getName(bool $code)
    {
        if(array_key_exists($code, self::$additionalList)) return self::$additionalList[$code];
        return Countries::getName($code);
    }

    public static function getNamesWithoutAddons() { return self::getNames(true); }
    public static function getNames($addons = false)
    {
        $countryList = Countries::getNames() + ($addons ? self::$additionalList : []);
        foreach(self::$rejectCountryList as $code)
            unset($countryList[$code]);

        return $countryList;
    }

    public static function getChoices(): array
    {
        return array_flip(self::getNames());
    }

    public static function getIcons(): array
    {
        return [];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choices' => $this->getChoices(),
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
            'alpha3' => false,
            'template' => "function (option) { if (!option.id) return option.text; return $('<span><img class=\"country-flag\" src=\"".$this->baseService->getUrl("bundles/base/flags/'+option.id+'.png")."\" alt=\"'+option.id+'\"> '+option.text+'</span>'); }",
            'invalid_message' => function (Options $options, $previousValue) {
                return 'Please select a valid country.';
            }
        ]);

    }

    /**
     * {@inheritdoc}
     */
    public function getParent() : ?string
    {
        return SelectType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'country';
    }
}
