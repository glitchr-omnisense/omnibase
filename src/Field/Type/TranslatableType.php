<?php

namespace Base\Field\Type;

use Base\Field\Type\TranslationType;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;

class TranslatableType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'translation_field_name' => "translations",
            'translation_class' => null,

            'fields' => [],
            'only_fields' => [],
            'excluded_fields' => [],

            "single_locale" => null,
            "default_locale" => null,
            "required_locales" => null,
            "available_locales" => null,

            // Implement this if you want to be able to handle
            // multiple translatable entities as a collection
            "multiple" => false
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $translationOptions = [];

        if($options["fields"])
            $translationOptions['fields'] = $options["fields"];
        if($options["only_fields"])
            $translationOptions['only_fields'] = $options["only_fields"];
        if($options["excluded_fields"])
            $translationOptions['excluded_fields'] = $options["excluded_fields"];

        if($options["single_locale"])
            $translationOptions['single_locale'] = $options["fields"];
        if($options["default_locale"])
            $translationOptions['default_locale'] = $options["default_locale"];
        if($options["required_locales"])
            $translationOptions['required_locales'] = $options["required_locales"];
        if($options["available_locales"])
            $translationOptions['available_locales'] = $options["available_locales"];

        $builder->add($options["translation_field_name"], TranslationType::class, $translationOptions);
    }
}
