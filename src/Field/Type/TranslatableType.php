<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Database\TranslatableInterface;
use Base\Exception\MissingLocaleException;
use Base\Field\Traits\SelectTypeInterface;
use Base\Field\Traits\SelectTypeTrait;
use Base\Service\BaseService;
use Base\Service\LocaleProviderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class TranslatableType extends AbstractType
{
    protected $fallbackLocales = [];

    protected $localeProvider = null;
    public function __construct(ClassMetadataManipulator $classMetadataManipulator, LocaleProviderInterface $localeProvider, BaseService $baseService)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->localeProvider = $localeProvider;
        $this->baseService = $baseService;
    }

    public function getDefaultLocale()
    {
        return $this->localeProvider->getDefaultLocale();
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'label' => false,
            
            'locale'            => $this->localeProvider->getLocale(),
            'locale_options'    => [],
            
            'single_locale'     => false,
            'default_locale'    => $this->localeProvider->getDefaultLocale(),
            'required_locales'  =>  [],
            'available_locales' => $this->localeProvider->getAvailableLocales(),

            'by_reference' => false,
            'empty_data' => fn(FormInterface $form) => new ArrayCollection,
            
            'fields' => [],
            'excluded_fields' => []
        ]);
    }
    
    public function getFields(FormInterface $form, array $options): array
    {
        $translatableClass = $this->getTranslationDataClass($form);
        $rawFields = $this->classMetadataManipulator->getFields($translatableClass, $options["fields"], $options["excluded_fields"]);

        $metadata = $this->classMetadataManipulator->getClassMetadata($translatableClass);
        $excludedFields = $options["excluded_fields"] ?? [];
        foreach($excludedFields as $field) {

            if(!$metadata->hasField($field) && !$metadata->hasAssociation($field))
                throw new \Exception("Field \"".$field."\" requested for exclusion doesn't exist in \"".$form->getName()."\"");
        }

        $locales = ($options["single_locale"] ? [$options["locale"]] : $options['available_locales']);
        if(count($locales) == 1) $defaultLocale = $locales[0];
        else $defaultLocale = $options["default_locale"];

        foreach ($rawFields as $fieldName => $fieldConfig) {

            $fieldConfig["required"] = $fieldConfig["required"] ?? false;

            // Simplest case: General options for all locales
            if (!isset($fieldConfig['locale_options'])) {

                foreach ($options['available_locales'] as $locale) {

                    $fields[$locale][$fieldName] = $fieldConfig;
                    $fields[$locale][$fieldName]["required"] &= \in_array($locale, $options['required_locales'], true) || $locale == $defaultLocale;
                }

                continue;
            }

            // Custom options by locale
            $localesFieldOptions = $fieldConfig['locale_options'];
            unset($fieldConfig['locale_options']);

            foreach ($options['available_locales'] as $locale) {

                $localeFieldOptions = $localesFieldOptions[$locale] ?? [];
                if (!isset($localeFieldOptions['display']) || (true === $localeFieldOptions['display']))
                    $fields[$locale][$fieldName] = $localeFieldOptions + $fieldConfig;

                $fields[$locale][$fieldName]["required"] &= \in_array($locale, $options['required_locales'], true) || $locale == $defaultLocale;
            }
        }

        return $fields;
    }

    private function getTranslationDataClass(FormInterface $form): string
    {
        $translatableClass = $form->getConfig()->getDataClass();
        $translatableClass = is_subclass_of($form->getConfig()->getDataClass(), TranslatableInterface::class) ? $form->getConfig()->getDataClass() : null;
        
        $formInit = $form;
        while($translatableClass === null) {

            if($form->getParent() === null) 
                throw new \Exception("No \"data_class\" found in FormType \"".$formInit->getName()."\" (".get_class($formInit->getConfig()->getType()->getInnerType()).")  or any of its parents");

            $translatableClass = $form->getParent()->getConfig()->getDataClass();
            $form = $form->getParent();
        };

        if(!$translatableClass)
            throw new \Exception("Missing \"data_class\" option in FormType \"".$form->getName()."\" (".get_class($form->getConfig()->getType()->getInnerType()).")");

        if(!is_subclass_of($translatableClass, TranslatableInterface::class))
            throw new \Exception("Translatable interface not implemented in \"".$translatableClass."\"");
    
        return $translatableClass::getTranslationEntityClass(true, false); //, false);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            if (null === $formParent = $form->getParent()) {
                throw new \RuntimeException('Parent form missing');
            }

            $options = $form->getConfig()->getOptions();

            $fields = $this->getFields($form, $options);
            $translationClass = $this->getTranslationDataClass($form);

            $unavailableRequiredLocales = array_diff($options['required_locales'], $options['available_locales']);
            if(!empty($unavailableRequiredLocales))
                throw new MissingLocaleException(
                    "The locale(s) \"".implode(",", $unavailableRequiredLocales)."\" are missing, but required by FormType \"".
                    $form->getName()."\" (".get_class($form->getConfig()->getType()->getInnerType())."). Available locales are: ".implode(",", $options["available_locales"]));

            $dataLocale = ($event->getData() ? $event->getData()->getKeys() : [$options["locale"]]);
            $locales = ($options["single_locale"] ? [$options["locale"]] : $options['available_locales']);
            foreach ($locales as $key => $locale) {

                if (!isset($fields[$locale]))
                    continue;

                $defaultLocale = $options["default_locale"];
                if($options["single_locale"] && \count($dataLocale) == 1)
                    $defaultLocale = $locale = $dataLocale[0];

                $required  = \in_array($locale, $options['required_locales'], true);
                $required |= ($locale == $defaultLocale);

                $form->add($locale, EntityType::class, [
                    'data_class' => $translationClass,
                    'required' => $required,
                    'fields' => $fields[$locale],
                    'excluded_fields' => $options['excluded_fields']
                ]);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $data = $event->getData();
            foreach ($data as $locale => $translation) {

                if ($translation === null)
                    $data->removeElement($translation);
                else if ($translation->isEmpty())
                    $data->removeElement($translation);
                else 
                    $translation->setLocale($locale);
            }

            $event->setData($data);
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["locale"]            = $options["locale"];
        $view->vars["single_locale"]     = $options["single_locale"];
    
        $view->vars["default_locale"]    = $options["default_locale"];
        $view->vars["available_locales"] = $options["available_locales"];

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-translatable.js");
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'translatable';
    }
}
