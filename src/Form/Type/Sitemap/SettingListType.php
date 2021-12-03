<?php

namespace Base\Form\Type\Sitemap;

use Base\Annotations\Annotation\Uploader;
use Base\Entity\Sitemap\Setting;
use Base\Entity\Sitemap\SettingTranslation;
use Base\Field\Type\AvatarType;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\FileType;
use Base\Field\Type\ImageType;
use Base\Field\Type\TranslationType;
use Base\Service\BaseService;
use Base\Service\BaseSettings;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class SettingListType extends AbstractType implements DataMapperInterface
{
    public function __construct(BaseSettings $baseSettings)
    {
        $this->baseSettings = $baseSettings;
    }

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
        else if(BaseService::array_is_associative($data)) {

            foreach($data as $name => $value)
                $newData[str_replace($from, $to, $name)] = $value;

        } else if( is_subclass_of($data, BaseSettings::class)) {

            foreach($data->all() as $setting)
                $newData[str_replace($from, $to, $setting->getName())] = $setting->getValue();

        } else if( is_subclass_of($data, Setting::class)) {

            $newData[str_replace($from, $to, $data->getName())] = $data->getValue();

        } else {

            throw new \Exception("Unexpected data provided (expecting either associative array, Setting or BaseSetting)");
        }

        return $newData;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDataMapper($this);
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) use ($options) {

            $form = $event->getForm();

            $formattedFields = $this->getFormattedData($options["fields"]);
            foreach($formattedFields as $formattedField => $fieldOptions) {

                $field = str_replace("-", ".", $formattedField);
                $this->baseSettings->remove($field);

                $settings[$formattedField] = $this->baseSettings->getRawScalar($field, $options["locale"]) ?? new Setting($field, "");
            }

            $translatableFields = [];
            $untranslatableFields = [];
            foreach($settings as $formattedField => $setting) {

                // Exclude requested fields
                $field = str_replace("-", ".", $formattedField);
                if(in_array($field, $options["excluded_fields"]))
                    continue;

                // Detect field form type class
                $class = $options["fields"][$field]["class"] ?? TextType::class;
                if(array_key_exists("class", $options["fields"][$field]))
                    unset($options["fields"][$field]["class"]);

                $settingValue = $setting->getValue();

                switch($class) {

                    case DateTimePickerType::class:
                        $settingValue = $settingValue ? new \DateTime($settingValue) : null;
                        break;

                    case CheckboxType::class:
                        $bool = !empty($settingValue) && $settingValue != "0";
                        $settingValue = $bool ? true : false;
                        break;
                }

                // Set field options
                $fieldOptions = $options["fields"][$field];
                $fieldOptions["attr"] = $opts["attr"] ?? [];

                // Set default label
                if(!array_key_exists("label", $fieldOptions)) {
                    $label = explode("-", $formattedField);
                    $fieldOptions["label"] = $setting->getLabel() ?? ucwords(str_replace("_", " ", BaseService::camelToSnakeCase(end($label))));
                }

                if($class == FileType::class || $class == ImageType::class || $class == AvatarType::class) {
                    $fieldOptions["max_filesize"] = $fieldOptions["max_filesize"] ?? Uploader::getMaxFilesize(SettingTranslation::class, "value");
                    $fieldOptions["mime_types"]   = $fieldOptions["mime_types"]   ?? Uploader::getMimeTypes(SettingTranslation::class, "value");
                    $fieldOptions["empty_data"]   = $settingValue ?? "";
                }

                if(!array_key_exists("help", $fieldOptions))
                    $fieldOptions["help"] = $setting->getHelp() ?? "";

                //
                // Check if expected to be translatable
                $isTranslatable = !in_array($field, $options["fields[single_locale]"]);
                if(array_key_exists("single_locale", $fieldOptions))
                    unset($fieldOptions["single_locale"]);

                if($isTranslatable) {

                    $translationFields[$formattedField] = $fieldOptions;
                    $translationData[$formattedField] = $setting->getTranslations();

                } else {

                    $intlFields[$formattedField] = $fieldOptions;
                    $intlData[$formattedField] = $setting->getTranslations();
                }
            }

            $form->add("intl", TranslationType::class, [
                "multiple" => true,
                "single_locale" => true,
                "translation_class" => SettingTranslation::class,
                "only_fields" => ["value"], 
                "fields" => [
                    "value" => ["form_type" => TextType::class]
                ],
            ]);
            
            $form->get("intl")->setData($intlData);

            $form->add("translations", TranslationType::class, [
                "multiple" => true,
                "translation_class" => SettingTranslation::class,
                "only_fields" => ["value"], 
                "fields" => [
                    "value" => ["form_type" => TextType::class]
                ],
            ]);
            $form->get("translations")->setData($translationData);

            // $form->add($formattedField, $class, $fieldOptions);
            // $form->get($formattedField)->setData($settingValue);
                
            // if($translatableFields) {

            //     $form->add("translations", TranslationType::class, [
            //         "fields" => $translatableFields,
            //         "translation_class" => SettingTranslation::class,
            //         "multiple" => true,
            //         "parent" => $event
            //     ]);

            //     $form->get("translations")->setData($translatableData);
            // }

            $form->add('valid', SubmitType::class);
        });
    }

    public function mapDataToForms($viewData, \Traversable $forms): void {}

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        $children = iterator_to_array($forms);

        $newViewData = [];
        foreach($children as $name => $child)
            $newViewData[$name] = $child->getData();

        $newViewData = $this->getFormattedData($newViewData, "-", ".");
        foreach($newViewData as $field => $value)
        {
            //dump($field, $value);
            if($field == "valid") continue;
            else if($field == "translations") {

                foreach($value as $locale => $newChildViewData) {

                    foreach($newChildViewData as $childField => $childValue) {

                        if(!$newChildViewData[$childField] instanceof Setting)
                        $newViewData[$childField] = $this->baseSettings->getRawScalar($childField) ?? new Setting($childField, "");
    
                        $formattedField = str_replace(".", "-", $childField);
                        $newViewData[$childField] = $newChildViewData[$field]->setValue($childValue ?? "", $locale);
        
                        $this->baseSettings->removeCache($childField);
                    }
                }

                unset($newViewData[$field]);
                
                dump("TRANSLATIONS !");
                dump($newViewData);
                exit(1);
                
            } else {

                if(!$newViewData[$field] instanceof Setting)
                    $newViewData[$field] = $this->baseSettings->getRawScalar($field) ?? new Setting($field, "");

                $formattedField = str_replace(".", "-", $field);
                $newViewData[$field] = $newViewData[$field]->setValue($children[$formattedField]->getViewData() ?? "");

                $this->baseSettings->removeCache($field);
            }
        }

        unset($newViewData["valid"]);
        $viewData = $newViewData;
    }
}
