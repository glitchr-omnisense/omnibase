<?php

namespace Base\Form\Type\Layout;

use Base\Annotations\Annotation\Uploader;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Entity\Layout\Setting;
use Base\Entity\Layout\SettingTranslation;
use Base\Field\Type\AvatarType;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\FileType;
use Base\Field\Type\ImageType;
use Base\Field\Type\TranslationType;
use Base\Service\SettingBag;
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
    /**
     * @var SettingBag
     */
    protected $settingBag;

    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    public function __construct(SettingBag $settingBag, ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->settingBag = $settingBag;
        $this->classMetadataManipulator = $classMetadataManipulator;
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
        if(!$data) return [];
        else if(is_associative($data)) {

            foreach($data as $name => $value)
                $newData[str_replace($from, $to, $name)] = $value;

        } else if( is_subclass_of($data, SettingBag::class)) {

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

            $settingBag = [];

            $formattedFields = $this->getFormattedData($options["fields"]);
            foreach($formattedFields as $formattedField => $fieldOptions) {

                $field = str_replace("-", ".", $formattedField);

                $settingBag[$formattedField] = $this->settingBag->getRawScalar($field, $options["locale"], false) ?? new Setting($field);
            }

            $fields = ["value" => []];

            $unvData = [];
            $intlData = [];

            foreach($settingBag as $formattedField => $setting) {

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
                    $label = explode("-", trim(str_lstrip($formattedField, ["app-settings", "base-settings"]), " -"));
                    $fieldOptions["label"] = $setting->getLabel() ?? mb_ucwords(str_replace("_", " ", implode(" - ", $label)));
                }

                if ($fieldOptions["form_type"] == FileType::class || $fieldOptions["form_type"] == ImageType::class || $fieldOptions["form_type"] == AvatarType::class) {
                    $fieldOptions["max_size"] = $fieldOptions["max_size"] ?? Uploader::getMaxFilesize(SettingTranslation::class, "value");
                    $fieldOptions["mime_types"]   = $fieldOptions["mime_types"]   ?? Uploader::getMimeTypes(SettingTranslation::class, "value");
                    $fieldOptions["empty_data"]   = $settingValue ?? "";
                }

                if(!array_key_exists("help", $fieldOptions))
                    $fieldOptions["help"] = $setting->getHelp() ?? "";
                if(!array_key_exists("disabled", $fieldOptions))
                    $fieldOptions["disabled"] = $setting->isLocked() ?? false;

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
                $form->add('valid', SubmitType::class, ["translation_domain" => "controllers", "label_format" => "backoffice_settings.valid"]);
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

                        $viewData[$field] = $viewData[$field] ?? new Setting($field);
                        if($viewData[$field]->isLocked())
                            throw new \Exception("Setting \"".$viewData[$field]->getPath()."\" is locked, you cannot edit this variable.");

                        $viewData[$field]->translate($locale)->setValue($translation->getValue() ?? null);
                    }
                }

                unset($viewData[$formName]);

            } else {

                $field = str_replace("-", ".", $formName);

                if(!$viewData[$formName] instanceof Setting)
                    $viewData[$formName] = $viewData[$formName] ?? new Setting($formName);

                if($viewData[$formName]->isLocked())
                    throw new \Exception("Setting \"".$viewData[$formName]->getPath()."\" is currently locked.");

                $translation = $form->getViewData();
                $locale      = $translation->getLocale();

                $viewData[$formName]->translate($locale)->setValue($translation->getValue() ?? null);
            }
        }
    }
}
