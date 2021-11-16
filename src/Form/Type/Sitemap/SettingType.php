<?php

namespace Base\Form\Type\Sitemap;

use Base\Annotations\Annotation\Uploader;
use Base\Entity\Sitemap\Setting;
use Base\Entity\Sitemap\SettingTranslation;
use Base\Field\Type\AvatarType;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\FileType;
use Base\Field\Type\ImageType;
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

class SettingType extends AbstractType implements DataMapperInterface
{
    public function __construct(BaseSettings $baseSettings)
    {
        $this->baseSettings = $baseSettings;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'fields' => [],
            'excluded_fields' => [],
            'locale' => null
        ]);
    }

    public function getFormattedData($data, $from = ".", $to = "-")
    {
        $newData = [];
        if(!$data) return [];
        else if(BaseService::isAssoc($data)) {

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
                $data[$formattedField] = $this->baseSettings->get($field, $options["locale"]);
            }

            foreach($data as $formattedField => $setting) {

                // Exclude requested fields
                $field = str_replace("-", ".", $formattedField);
                if(in_array($field, $options["excluded_fields"]))
                    continue;

                // Detect field form type class
                $class = $options["fields"][$field]["class"] ?? TextType::class;
                if(array_key_exists("class", $options["fields"][$field]))
                    unset($options["fields"][$field]["class"]);

                // Set field options
                $fieldOptions = $options["fields"][$field];
                $fieldOptions["attr"] = $opts["attr"] ?? [];

                // Set default label
                if(!array_key_exists("label", $fieldOptions)) {
                    $label = explode("-", $formattedField);
                    $fieldOptions["label"] = ucwords(str_replace("_", " ", BaseService::camelToSnakeCase(end($label))));
                }

                if(!array_key_exists("label", $fieldOptions)) {
                    $label = explode("-", $formattedField);
                    $fieldOptions["label"] = ucwords(str_replace("_", " ", BaseService::camelToSnakeCase(end($label))));
                }

                if($class == FileType::class || $class == ImageType::class || $class == AvatarType::class) {
                    $fieldOptions["max_filesize"] = $fieldOptions["max_filesize"] ?? Uploader::getMaxFilesize(SettingTranslation::class, "value");
                    $fieldOptions["mime_types"]   = $fieldOptions["mime_types"]   ?? Uploader::getMimeTypes(SettingTranslation::class, "value");
                    $fieldOptions["empty_data"]   = $setting;
                }

                $form->add($formattedField, $class, $fieldOptions);
                $value = $setting;

                switch($class) {

                    case DateTimePickerType::class:
                        $form->get($formattedField)->setData(($value ? new \DateTime($value) : null));
                        break;

                    case CheckboxType::class:
                        $bool = !empty($value) && $value != "0";
                        $form->get($formattedField)->setData($bool ? true : false);
                        break;

                    default:
                        $form->get($formattedField)->setData($value);
                }
            }

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
            if($field == "valid") continue;

            if(!$newViewData[$field] instanceof Setting)
                $newViewData[$field] = $this->baseSettings->getSettings($field) ?? new Setting($field, "");

            $formattedField = str_replace(".", "-", $field);
            $newViewData[$field] = $newViewData[$field]->setValue($children[$formattedField]->getViewData() ?? "");

            $this->baseSettings->removeCache($field);
        }

        $viewData = $newViewData;
    }
}
