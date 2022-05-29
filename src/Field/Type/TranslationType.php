<?php

namespace Base\Field\Type;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Database\TranslatableInterface;
use Base\Database\TranslationInterface;

use Base\Service\BaseService;
use Base\Service\LocaleProviderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Traversable;
use UnexpectedValueException;

class TranslationType extends AbstractType implements DataMapperInterface
{
    /**
     * @var LocaleProviderInterface
     */
    protected $localeProvider = null;
    protected $fallbackLocales = [];

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator = null;

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

            'autoload' => true,
            'fields' => [],
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
        $builder->setDataMapper($this);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            $options = $form->getConfig()->getOptions();
            $locales = ($options["single_locale"] ? [$options["locale"]] : $options['available_locales']);
            $dataLocale = $data instanceof Collection ? $data->getKeys() : [$options["locale"]];

            $translationClass = $this->getTranslationClass($form);
            $translationFields = $this->getTranslationFields($translationClass, $options);

            foreach ($locales as $key => $locale) {

                if (!isset($translationFields[$locale]))
                    continue;

                $entityOptions = [
                    'class' => $translationClass,
                    'autoload' => $options["autoload"],
                    'fields' => $translationFields[$locale],
                    'excluded_fields' => $options['excluded_fields'],
                    'keep_indexes' => false
                ];

                $defaultLocale = $options["default_locale"];
                if($options["single_locale"] && \count($dataLocale) == 1)
                    $defaultLocale = $locale = $dataLocale[0];

                if($locale == $defaultLocale || in_array($locale, $options['required_locales'], true))
                    $entityOptions["entry_required"] = true;
                else if(!empty($options['required_locales'])) // /!\ Telling required locales explicitely makes all subfields optionals
                    $entityOptions["entry_required"] = in_array($locale, $options['required_locales'], true);

                if($options["multiple"]) {

                    $entityOptions["multiple"] = true;
                    $entityOptions["group"] = false;
                    $entityOptions["row_group"] = false;
                    $entityOptions["allow_add"] = false;
                    $entityOptions["allow_delete"] = false;
                    $entityOptions["recursive"] = true;
                }

                $form->add($locale, AssociationType::class, $entityOptions);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $data = $event->getData();
            $multiple = $event->getForm()->getConfig()->getOption("multiple");
            if(!$multiple) $data = [$data];

            foreach($data as $key => $array) {

                foreach ($array as $locale => $translation) {

                    if($array instanceof PersistentCollection) {

                        if (!$translation instanceof TranslationInterface) $data[$key]->removeElement($translation);
                        else if ($translation->isEmpty()) $data[$key]->removeElement($translation);
                        else $translation->setLocale($locale);

                    } else {

                        if (!$translation instanceof TranslationInterface) unset($data[$key][$locale]);
                        else if (empty($translation)) unset($data[$key][$locale]);
                    }
                }
            }

            $event->setData(!$multiple ? $data[0] : $data);
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars["locale"]            = $options["locale"];
        $view->vars["single_locale"]     = $options["single_locale"];

        $view->vars["default_locale"]    = $options["default_locale"];
        $view->vars["available_locales"] = $options["available_locales"];
        $view->vars["required_locales"]  = $options["required_locales"];

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
        // Check excluded fields exists.. (for dev sanity)
        $classMetadata = $this->classMetadataManipulator->getClassMetadata($translationClass);
        $excludedFields = $options["excluded_fields"] ?? [];
        foreach($excludedFields as $field) {

            if(!property_exists($classMetadata->getName(),$field))
                throw new \Exception("Field \"".$field."\" requested for exclusion doesn't exist in \"".$translationClass."\"");
        }

        // Prepare raw fields
        $fields = $this->classMetadataManipulator->getFields($translationClass, $options["fields"], $options["excluded_fields"]);
        if(!$options["autoload"])
            $fields = array_filter($fields, fn($k) => array_key_exists($k, $options["fields"]), ARRAY_FILTER_USE_KEY);

        // Compute fields including locale information
        $translationFields = [];
        foreach ($fields as $fieldName => $fieldConfig) {

            // Simplest case: General options for all locales
            if (!isset($fieldConfig['locale_options'])) {

                foreach ($options['available_locales'] as $locale)
                    $translationFields[$locale][$fieldName] = $fieldConfig;

                continue;
            }

            // Custom options by locale
            $localesFieldOptions = $fieldConfig['locale_options'];
            unset($fieldConfig['locale_options']);

            foreach ($options['available_locales'] as $locale) {

                $localeFieldOptions = $localesFieldOptions[$locale] ?? [];
                if (!isset($localeFieldOptions['display']) || (true === $localeFieldOptions['display']))
                    $translationFields[$locale][$fieldName] = $localeFieldOptions + $fieldConfig;
            }
        }

        return $translationFields;
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

    public function mapDataToForms($viewData, Traversable $forms)
    {
        $multiple = current(iterator_to_array($forms))->getParent()->getConfig()->getOption("multiple");
        foreach(iterator_to_array($forms) as $locale => $form) {

            if(!$multiple) $form->setData($viewData[$locale] ?? null);
            else {

                $newViewData = new ArrayCollection();
                foreach($viewData ?? [] as $key => $data)
                    $newViewData[$key] = $data[$locale] ?? null;

                $form->setData($newViewData);
            }
        }
    }

    public function mapFormsToData(Traversable $forms, &$viewData)
    {
        $multiple = current(iterator_to_array($forms))->getParent()->getConfig()->getOption("multiple");

        foreach(iterator_to_array($forms) as $locale => $form) {

            $data = $form->getData();
            if(!$multiple) {

                if ($data instanceof TranslationInterface) {

                    if($data->isEmpty()) unset($viewData[$locale]);
                    else {

                        $viewData[$locale] = is_array($data) && empty($data) ? null : $data;
                        $viewData[$locale]->setLocale($locale);
                    }
                }

            } else {

                foreach($data as $key => $translation) {

                    if ( $translation === null) continue;
                    if(!($translation instanceof TranslationInterface))
                        throw new UnexpectedValueException("Object expected to be an instance of TranslationInterface, \"".(is_object($translation) ? get_class($translation) : $translation)."\" received.");

                    $translation->setLocale($locale);

                    if($viewData[$key] instanceof PersistentCollection) {

                        $translatable = $viewData[$key]->getOwner();
                        if ($translatable instanceof TranslatableInterface)
                            $translation->setTranslatable($translatable);
                    }

                    if($translation) $viewData[$key][$locale] = $translation;
                }
            }
        }
    }
}
