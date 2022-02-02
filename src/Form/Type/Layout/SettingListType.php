<?php

namespace Base\Form\Type\Layout;

use Base\Annotations\Annotation\Uploader;
use Base\Database\TranslationInterface;
use Base\Entity\Layout\Setting;
use Base\Entity\Layout\SettingTranslation;
use Base\Field\Type\AvatarType;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\FileType;
use Base\Field\Type\ImageType;
use Base\Field\Type\TranslationType;
use Base\Service\BaseSettings;
use Base\Service\LocaleProvider;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class SettingListType extends AbstractType implements DataMapperInterface
{
    public function __construct(BaseSettings $baseSettings) { $this->baseSettings = $baseSettings; }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'fields' => [],
            'fields[single_locale]' => [],
            'excluded_fields' => [],
            'locale' => null
        ]);
    }

    public function getFormattedData($data, $from = ".", $to = "-")
    {
        $newData = [];
        if(!$data) return [];
        else if(is_associative($data)) {

            foreach($data as $name => $value)
                $newData[str_replace($from, $to, $name)] = $value;

        } else if( is_subclass_of($data, BaseSettings::class)) {

            foreach($data->all() as $setting)
                $newData[str_replace($from, $to, $setting->getPath())] = $setting->getValue();

        } else if( is_subclass_of($data, Setting::class)) {

            $newData[str_replace($from, $to, $data->getPath())] = $data->getValue();

        } else {

            throw new \Exception("Unexpected data provided (expecting either associative array, Setting or BaseSetting)");
        }

        return $newData;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) use ($options) {

            $settings = [];

            $formattedFields = $this->getFormattedData($options["fields"]);
            foreach($formattedFields as $formattedField => $fieldOptions) {

                $field = str_replace("-", ".", $formattedField);
                
                $settings[$formattedField] = $this->baseSettings->getRawScalar($field, $options["locale"]) ?? new Setting($field);
            }

            $fields = ["value" => []];

            $unvData = [];
            $intlData = [];
            foreach($settings as $formattedField => $setting) {

                // Exclude requested fields
                $field = str_replace("-", ".", $formattedField);
                if(in_array($field, $options["excluded_fields"]))
                    continue;

                // Set field options
                $fieldOptions = $options["fields"][$field];
                $fieldOptions["attr"] = $opts["attr"] ?? [];
                $fieldOptions["form_type"] = $fieldOptions["form_type"] ?? TextType::class;

                // Set default label
                if(!array_key_exists("label", $fieldOptions)) {
                    $label = explode("-", $formattedField);
                    $fieldOptions["label"] = $setting->getLabel() ?? mb_ucwords(str_replace("_", " ", camel_to_snake(end($label))));
                }

                if ($fieldOptions["form_type"] == FileType::class || $fieldOptions["form_type"] == ImageType::class || $fieldOptions["form_type"] == AvatarType::class) {
                    $fieldOptions["max_filesize"] = $fieldOptions["max_filesize"] ?? Uploader::getMaxFilesize(SettingTranslation::class, "value");
                    $fieldOptions["mime_types"]   = $fieldOptions["mime_types"]   ?? Uploader::getMimeTypes(SettingTranslation::class, "value");
                    $fieldOptions["empty_data"]   = $settingValue ?? "";
                }

                if(!array_key_exists("help", $fieldOptions))
                    $fieldOptions["help"] = $setting->getHelp() ?? "";

                //
                // Check if expected to be translatable
                $isTranslatable = $fieldOptions["translatable"] ?? false;
                $fieldOptions = array_key_removes($fieldOptions, "translatable");

                $fields["value"][$formattedField] = $fieldOptions;

                $translations = $setting->getTranslations();
                foreach($translations as $_ => $settingTranslation) {

                    $settingValue = $settingTranslation->getValue();
                    switch($fieldOptions["form_type"]) {

                        case DateTimePickerType::class:
                            $datetime = $settingValue instanceof \DateTime ? $settingValue : null;
                            if(!$datetime) $datetime = $settingValue ? new \DateTime($settingValue) : null;
                            $settingTranslation->setValue($datetime);
                            break;

                        case CheckboxType::class:
                            $bool = !empty($settingValue) && $settingValue != "0";
                            $settingTranslation->setValue($bool ? true : false);
                            break;
                    }
                }

                if ($isTranslatable) $intlData[$formattedField] = $translations;
                else $unvData[$formattedField] = $translations;
            }

            $form = $event->getForm();
            if($intlData) {

                $form->add("intl", TranslationType::class, [
                    "fields" => $fields,
                    "autoload" => false,
                    "multiple" => true,
                    "required_locales" => [LocaleProvider::getDefaultLocale()],
                    "translation_class" => SettingTranslation::class,
                ]);

                $form->get("intl")->setData($intlData);
            }

            if($unvData) {

                $form->add("unv", TranslationType::class, [
                    "fields" => $fields,
                    "autoload" => false,
                    "multiple" => true,
                    "single_locale" => true,
                    "translation_class" => SettingTranslation::class,
                ]);

                $form->get("unv")->setData($unvData);
            }

            if(count($fields) > 0)
                $form->add('valid', SubmitType::class, ["translation_domain" => "controllers", "label_format" => "dashboard_settings.valid"]);
        });
    }

    public function mapDataToForms($viewData, \Traversable $forms): void { }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        foreach(iterator_to_array($forms) as $formName => $form)
        {
            if($formName == "valid") continue;
            else if($formName == "intl" || $formName == "unv") {

                foreach($form->getData() as $formattedField => $translations) {

                    $field = str_replace("-", ".", $formattedField);
                    foreach($translations as $locale => $translation) {

                        $viewData[$field] = $viewData[$field] ?? $this->baseSettings->getRawScalar($formattedField) ?? new Setting($field);
                        $viewData[$field]->translate($locale)->setValue($translation->getValue() ?? null);
                    }
                 }

                unset($viewData[$formName]);
                
            } else {

                if(!$viewData[$formName] instanceof Setting)
                    $viewData[$formName] = $viewData[$formName] ?? $this->baseSettings->getRawScalar($formName) ?? new Setting($formName);

                $translation = $form->getViewData();
                $locale      = $translation->getLocale();

                $field = str_replace("-", ".", $formName);
                $viewData[$formName] = $viewData[$formName]->translate($locale)->setValue($translation->getValue() ?? null);
            }
        }
    }
}
