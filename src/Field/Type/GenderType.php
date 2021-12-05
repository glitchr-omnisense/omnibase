<?php

namespace Base\Field\Type;

use Base\Service\BaseService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GenderType extends AbstractType
{
    public function getParent() : ?string { return SelectType::class; }
    public function getBlockPrefix(): string { return 'gender'; }

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
