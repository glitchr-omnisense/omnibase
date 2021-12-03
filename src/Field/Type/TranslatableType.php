<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Database\TranslatableInterface;
use Base\Database\TranslationInterface;
use Base\Exception\MissingLocaleException;
use Base\Field\Traits\SelectTypeInterface;
use Base\Field\Traits\SelectTypeTrait;
use Base\Service\BaseService;
use Base\Service\LocaleProviderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
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

    public function getBlockPrefix(): string { return 'translatable'; }
    public function getDefaultLocale() { return $this->localeProvider->getDefaultLocale(); }

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
            'only_fields' => [],
            'excluded_fields' => [],

            'translation_class' => null,
            'multiple' => false
        ]);

        $resolver->setNormalizer('required_locales', function (Options $options, $requiredLocales) {
            return array_intersect($requiredLocales, $options['available_locales']);
        });
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            $options = $form->getConfig()->getOptions();

            $locales = ($options["single_locale"] ? [$options["locale"]] : $options['available_locales']);
            $dataLocale = $data instanceof Collection ? $data->getKeys() : [$options["locale"]];

            $translationClass = $this->getTranslationClass($form);
            $fields = $this->getTranslationFields($translationClass, $options);

            foreach ($locales as $key => $locale) {

                if (!isset($fields[$locale]))
                    continue;

                $entityOptions = [
                    'fields' => $fields[$locale],
                    'class' => $translationClass,
                    'excluded_fields' => $options['excluded_fields']
                ];

                $defaultLocale = $options["default_locale"];
                if($options["single_locale"] && \count($dataLocale) == 1)
                    $defaultLocale = $locale = $dataLocale[0];

                if($locale != $defaultLocale && !in_array($locale, $options['required_locales'], true))
                    $entityOptions["required"] = false;
                
                dump($entityOptions,$options["multiple"], $options["translation_class"]);
                dump($data);
                $form->add($locale, EntityType::class, $entityOptions);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $data = $event->getData();
            foreach ($data as $locale => $translation) {

                if($translation instanceof Collection) {

                    if ($translation === null) $data->removeElement($translation);
                    else if ($translation->isEmpty()) $data->removeElement($translation);
                    else $translation->setLocale($locale);

                } else {

                    if ($translation === null) unset($data[$locale]);
                    else if (empty($translation)) unset($data[$locale]);
                }
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

        $view->vars["translations_empty"] = true;
        foreach($form->all() as $childForm) {

            if( count($childForm->all()) ) {
                $view->vars["translations_empty"] = false;
                break;
            }
        }

        $this->baseService->addHtmlContent("javascripts:body", "bundles/base/form-type-translatable.js");
    }

    public function getTranslationFields(string $translationClass, array $options): array
    {        
        $rawFields = $this->classMetadataManipulator->getFields($translationClass, $options["only_fields"], $options["excluded_fields"]);
        foreach($options["fields"] as $fieldName => $field)
            if(array_key_exists($fieldName, $rawFields)) $rawFields[$fieldName] = $field;

        $metadata = $this->classMetadataManipulator->getClassMetadata($translationClass);
        $excludedFields = $options["excluded_fields"] ?? [];
        foreach($excludedFields as $field) {

            if(!$metadata->hasField($field) && !$metadata->hasAssociation($field))
                throw new \Exception("Field \"".$field."\" requested for exclusion doesn't exist in \"".$translationClass."\"");
        }

        foreach ($rawFields as $fieldName => $fieldConfig) {

            // Simplest case: General options for all locales
            if (!isset($fieldConfig['locale_options'])) {

                foreach ($options['available_locales'] as $locale)
                    $fields[$locale][$fieldName] = $fieldConfig;

                continue;
            }

            // Custom options by locale
            $localesFieldOptions = $fieldConfig['locale_options'];
            unset($fieldConfig['locale_options']);

            foreach ($options['available_locales'] as $locale) {

                $localeFieldOptions = $localesFieldOptions[$locale] ?? [];
                if (!isset($localeFieldOptions['display']) || (true === $localeFieldOptions['display']))
                    $fields[$locale][$fieldName] = $localeFieldOptions + $fieldConfig;
            }
        }

        return $fields;
    }

    private function getTranslationClass(FormInterface $form): string
    {
        // Looking at translation class
        $translationClass = $form->getConfig()->getOption("translation_class");
        if(is_subclass_of($translationClass, TranslationInterface::class))
            return $translationClass;

        // Try to determine the translatable interface based on data_class from form
        $translatableClass = $this->getTranslatableClass($form);

        // Determine from parent too
        $formInit = $form;
        while($translatableClass === null) {

            if($form->getParent() === null) break;
            
            $translatableClass = $this->getTranslatableClass($form->getParent());
            $form = $form->getParent();
        };

        if(!$translatableClass)
            throw new \Exception("No \"translation_class\" found in \"".$form->getName()."\" and no hint (data_class or data) from inherited from FormType \"".$formInit->getName()."\" (".get_class($formInit->getConfig()->getType()->getInnerType()).") or any of its parents");

        if(!is_subclass_of($translatableClass, TranslatableInterface::class))
            throw new \Exception("Translatable interface not implemented in \"".$translatableClass."\"");
    
        return $translatableClass::getTranslationEntityClass(true, false); //, false);
    }

    private function getTranslatableClass(FormInterface $form)
    {
        // Looking at translatable interface using data_class
        $translatableClass = $form->getConfig()->getDataClass();
        if(is_subclass_of($translatableClass, TranslatableInterface::class)) 
            return $translatableClass;

        // Looking at translatable interface using data
        $translatableClass = $form->getConfig()->getData();
        if(is_subclass_of($translatableClass, TranslatatableInterface::class))
            return get_class($translatableClass);

        return null;
    }
}
