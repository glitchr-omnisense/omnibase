<?php

namespace Base\Field\Type;

use Base\Database\TranslatableInterface;
use Base\Field\Traits\SelectTypeInterface;
use Base\Field\Traits\SelectTypeTrait;
use Base\Service\BaseService;
use Base\Service\LocaleProviderInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslatableType extends AbstractType
{
    protected $defaultLocale = null;
    protected $fallbackLocales = [];
    public function __construct(LocaleProviderInterface $localeProvider)
    {
        $this->localeProvider = $localeProvider;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["locale"]           = $this->localeProvider->getLocale();
        $view->vars["defaultLocale"]    = $this->localeProvider->getDefaultLocale();
        $view->vars["availableLocales"] = $this->localeProvider->getAvailableLocales();
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'compound' => false
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'translatable';
    }

}