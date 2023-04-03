<?php

namespace Base\Form\Type;

use Base\Annotations\Annotation\Uploader;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Entity\Layout\Setting;
use Base\Entity\Layout\SettingIntl;
use Base\Field\Type\AvatarType;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\FileType;
use Base\Field\Type\ImageType;
use Base\Field\Type\TranslationType;
use Base\Form\Common\AbstractType;
use Base\Service\SettingBag;
use Base\Service\Localizer;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Util\StringUtil;

class LayoutSettingListType extends AbstractType implements DataMapperInterface
{
    /**
     * @var SettingBag
     */
    protected $settingBag;

    /**
     * @var Localizer
     */
    protected $localizer;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    public function __construct(SettingBag $settingBag, Localizer $localizer, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->settingBag = $settingBag;
        $this->localizer = $localizer;
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getBlockPrefix(): string
    {
        return "_base_".StringUtil::fqcnToBlockPrefix(static::class) ?: '';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'fields' => [],
            'fields[single_locale]' => [],
            'excluded_fields' => [],
            'locale' => null,
            'attr' => array(
                'class' => 'needs-validation'
            )
        ]);
    }

    public function getFormattedData($data, $from = ".", $to = "-")
    {
        $newData = [];
        if (!$data) {
            return [];
        } elseif (is_associative($data)) {
            foreach ($data as $name => $value) {
                $newData[str_replace($from, $to, $name)] = $value;
            }
        } elseif (is_subclass_of($data, SettingBag::class)) {
            foreach ($data->all() as $setting) {
                $newData[str_replace($from, $to, $setting->getPath())] = $setting->getValue();
            }
        } elseif (is_subclass_of($data, Setting::class)) {
            $newData[str_replace($from, $to, $data->getPath())] = $data->getValue();
        } else {
            throw new \Exception("Unexpected data provided (expecting either associative array, Setting or BaseSetting)");
        }

        return $newData;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {

            $settingBag = [];

            $formattedFields = $this->getFormattedData($options["fields"]);
            foreach ($formattedFields as $formattedField => $fieldOptions) {
                $field = str_replace("-", ".", $formattedField);

                $settingBag[$formattedField] = $this->settingBag->getRawScalar($field, false) ?? new Setting($field);
            }

            $fields = ["value" => null];

            $unvData = [];
            $intlData = [];

            foreach ($settingBag as $formattedField => $setting) {

                // Exclude requested fields
                $field = str_replace("-", ".", $formattedField);
                if (in_array($field, $options["excluded_fields"])) {
                    continue;
                }

                // Set field options
                $fieldOptions = $options["fields"][$field];
                $fieldOptions["attr"] = $opts["attr"] ?? [];
                $fieldOptions["form_type"] = $fieldOptions["form_type"] ?? TextType::class;

                // Set default label
                if (!array_key_exists("label", $fieldOptions)) {
                    $label = explode("-", trim(str_lstrip($formattedField, ["app-settings", "base-settings"]), " -"));
                    $fieldOptions["label"] = $setting->getLabel() ?? mb_ucwords(str_replace("_", " ", implode(" - ", $label)));
                }

                if ($fieldOptions["form_type"] == FileType::class || $fieldOptions["form_type"] == ImageType::class || $fieldOptions["form_type"] == AvatarType::class) {
                    $fieldOptions["max_size"] = $fieldOptions["max_size"] ?? Uploader::getMaxFilesize(SettingIntl::class, "value");
                    $fieldOptions["mime_types"]   = $fieldOptions["mime_types"]   ?? Uploader::getMimeTypes(SettingIntl::class, "value");
                    $fieldOptions["empty_data"]   = $settingValue ?? "";
                }

                if (!array_key_exists("help", $fieldOptions)) {
                    $fieldOptions["help"] = $setting->getHelp() ?? "";
                }
                if (!array_key_exists("disabled", $fieldOptions)) {
                    $fieldOptions["disabled"] = $setting->isLocked() ?? false;
                }

                //
                // Check if expected to be translatable
                $isTranslatable = $fieldOptions["translatable"] ?? false;
                $fieldOptions = array_key_removes($fieldOptions, "translatable");

                $fields["value"][$formattedField] = $fieldOptions;

                $translations = $setting->getTranslations();
                foreach ($translations as $locale => $settingTranslation) {

                    $settingValue = $settingTranslation->getValue();

                    switch($fieldOptions["form_type"]) {

                        case DateTimePickerType::class:
                            $datetime = $settingValue instanceof \DateTime ? $settingValue : null;
                            if (!$datetime) {
                                $datetime = $settingValue ? new \DateTime($settingValue) : null;
                            }
                            $settingTranslation->setValue($datetime);
                            break;

                        case CheckboxType::class:
                            $bool = !empty($settingValue) && $settingValue != "0";
                            $settingTranslation->setValue($bool ? true : false);
                            break;
                    }
                }

                if ($isTranslatable) {
                    $intlData[$formattedField] = $translations;
                } else {
                    $unvData[$formattedField] = $translations;
                }
            }

            $form = $event->getForm();
            if ($intlData) {

                $form->add("intl", TranslationType::class, [
                    "fields" => $fields,
                    "autoload" => false,
                    "multiple" => true,
                    "required_locales" => [$this->localizer->getDefaultLocale()],
                    "translation_class" => SettingIntl::class,
                ]);

                $form->get("intl")->setData($intlData);
            }

            if ($unvData) {

                $form->add("unv", TranslationType::class, [
                    "fields" => $fields,
                    "autoload" => false,
                    "multiple" => true,
                    "locale" => $this->localizer->getDefaultLocale(),
                    "single_locale" => true,
                    "translation_class" => SettingIntl::class,
                ]);

                $form->get("unv")->setData($unvData);
            }

            if (count($fields) > 0) {
                $form->add('valid', SubmitType::class, ["translation_domain" => "controllers", "label_format" => "backoffice_settings.valid"]);
            }
        });
    }

    public function mapDataToForms($viewData, \Traversable $forms): void
    {
    }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        foreach (iterator_to_array($forms) as $formName => $form) {

            if ($formName == "valid") {

                continue;

            } elseif ($formName == "intl" || $formName == "unv") {

                foreach ($form->getData() as $formattedField => $translations) {

                    $field = str_replace("-", ".", $formattedField);
                    foreach ($translations as $locale => $translation) {

                        $viewData[$field] = $viewData[$field] ?? $this->settingBag->getRawScalar($field);
                        if ($viewData[$field]->isLocked()) {
                            throw new \Exception("Setting \"".$viewData[$field]->getPath()."\" is locked, you cannot edit this variable.");
                        }

                        if($translation->getValue() == []) $translation->setValue(null);
                        $viewData[$field]->translate($locale)->setValue($translation->getValue() ?? null, $locale);
                    }
                }

            } else {

                $field = str_replace("-", ".", $formName);
                if (!$viewData[$formName] instanceof Setting) {
                    $viewData[$formName] = $viewData[$formName] ?? new Setting($formName);
                }

                if ($viewData[$formName]->isLocked()) {
                    throw new \Exception("Setting \"".$viewData[$formName]->getPath()."\" is currently locked.");
                }

                $translation = $form->getViewData();
                $locale      = $translation->getLocale();

                if($translation->getValue() == []) $translation->setValue(null);
                $viewData[$formName]->translate($locale)->setValue($translation->getValue() ?? null, $locale);
            }

        }
    }
}
