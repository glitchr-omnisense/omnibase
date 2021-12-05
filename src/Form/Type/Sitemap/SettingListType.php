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
use Doctrine\Common\Collections\ArrayCollection;
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
        else if(array_is_associative($data)) {

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

            $fields = [];
            $fields["value"] = [];
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
                    $fieldOptions["label"] = $setting->getLabel() ?? ucwords(str_replace("_", " ", camel_to_snake(end($label))));
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
                $isTranslatable = !in_array($field, $options["fields[single_locale]"]);
                if(array_key_exists("single_locale", $fieldOptions))
                    unset($fieldOptions["single_locale"]);

                $fields["value"][$formattedField] = $fieldOptions;

                $translations = $setting->getTranslations();
                foreach($translations as $locale => $settingTranslation) {

                    $settingValue = $settingTranslation->getValue();
                    switch($fieldOptions["form_type"]) {

                        case DateTimePickerType::class:
                            $settingTranslation->setValue(($settingValue ? new \DateTime($settingValue) : null));
                            break;

                        case CheckboxType::class:
                            $bool = !empty($settingValue) && $settingValue != "0";
                            $settingTranslation->setValue($bool ? true : false);
                            break;
                    }
                }

                if($isTranslatable) $translationData[$formattedField] = $translations;
                else $intlData[$formattedField] = $translations;
            }

            $form->add("intl", TranslationType::class, [
                "multiple" => true,
                "single_locale" => true,
                "translation_class" => SettingTranslation::class,
                "only_fields" => ["value"], 
                "fields" => $fields,
            ]);
            $form->get("intl")->setData($intlData);

            $form->add("translations", TranslationType::class, [
                "multiple" => true,
                "translation_class" => SettingTranslation::class,
                "only_fields" => ["value"], 
                "fields" => $fields,
            ]);
            $form->get("translations")->setData($translationData);

            if(count($fields) > 0) 
                $form->add('valid', SubmitType::class);
        });
    }

    public function mapDataToForms($viewData, \Traversable $forms): void { }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        foreach(iterator_to_array($forms) as $formName => $form)
        {
            if($formName == "valid") continue;
            else if($formName == "translations" || $formName == "intl") {

                foreach($form->getData() as $formattedField => $translations) {
                    
                    $field = str_replace("-", ".", $formattedField);
                    foreach($translations as $locale => $translation) {

                        $viewData[$field] = $this->baseSettings->getRawScalar($formattedField) ?? new Setting($field, "");
                        $viewData[$field]->setValue($translation->getValue() ?? "", $locale);

                        $this->baseSettings->removeCache($formattedField);
                    }
                 }

                unset($viewData[$formName]);
                
            } else {

                if(!$viewData[$formName] instanceof Setting)
                    $viewData[$formName] = $this->baseSettings->getRawScalar($formName) ?? new Setting($formName, "");

                $field = str_replace("-", ".", $formName);
                $viewData[$formName] = $viewData[$formName]->setValue($form->getViewData() ?? "");

                $this->baseSettings->removeCache($formName);
            }
        }
    }
}
