<?php

namespace Base\Form\Extension;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\AbstractTypeExtension;

class FormTypeTranslateExtension extends AbstractTypeExtension
{
    protected $defaultEnabled;
    public function __construct(bool $defaultEnabled = false)
    {
        $this->defaultEnabled = $defaultEnabled;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            "translation_domain" => false
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
