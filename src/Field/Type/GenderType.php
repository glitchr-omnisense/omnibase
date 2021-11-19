<?php

namespace Base\Field\Type;

use Base\Field\Traits\SelectTypeInterface;
use Base\Field\Traits\SelectTypeTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Config\Definition\Exception\Exception;

class GenderType extends AbstractType implements SelectTypeInterface
{
    use SelectTypeTrait;

    public static function getChoices(): array
    {
        return [
            "Male"       => "mars",
            "Female"     => "venus",
            "Hybrid"     => "mercury",
            "Genderless" => "genderless"
        ];
    }

    public static function getIcons(): array
    {
        return [
            "Male"       => "fa fa-fw fa-mars",
            "Female"     => "fa fa-fw fa-venus",
            "Hybrid"     => "fa fa-fw fa-mercury",
            "Genderless" => "fa fa-fw fa-genderless"
        ];
    }

    public function configureOptions(OptionsResolver $resolver) {

        $resolver->setDefaults([
            'choices' => self::getChoices(),
            'choice_icons' => self::getIcons(),
            'empty_data'   => "genderless",
            'invalid_message' => function (Options $options, $previousValue) {
                    return 'Please select a gender.';
            }
        ]);
    }
}
