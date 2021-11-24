<?php

namespace Base\Form\Type\Sitemap;

use Base\Annotations\Annotation\Uploader;
use Base\Entity\Sitemap\Setting;
use Base\Entity\Sitemap\SettingTranslation;
use Base\Entity\Sitemap\Widget;
use Base\Field\Type\AvatarType;
use Base\Field\Type\EntityType;
use Base\Field\Type\FileType;
use Base\Field\Type\ImageType;
use Base\Service\BaseService;
use Base\Service\BaseSettings;
use Base\Service\WidgetProviderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class WidgetListType extends AbstractType implements DataMapperInterface
{
    public function __construct(WidgetProviderInterface $widgetProvider)
    {
        $this->widgetProvider = $widgetProvider;
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

        } else if( is_subclass_of($data, Widget::class)) {

            $newData[str_replace($from, $to, $data->getName())] = $data->getName();

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
                $widgetSlots[$formattedField] = $this->widgetProvider->getWidgetSlot($field);
            }

            foreach($widgetSlots as $formattedField => $slot) {

                $slotLabel = ($slot ? $slot->getLabel() : null);
                $slotHelp  = ($slot ? $slot->getHelp()  : null);
                $slotValue = ($slot ? $slot->getName() : null);

                // Exclude requested fields
                $field = str_replace("-", ".", $formattedField);
                if(in_array($field, $options["excluded_fields"]))
                    continue;

                // Detect field form type class
                $class = $options["fields"][$field]["class"] ?? EntityType::class;
                if(array_key_exists("class", $options["fields"][$field]))
                    unset($options["fields"][$field]["class"]);

                // Set field options
                $fieldOptions = $options["fields"][$field];
                $fieldOptions["attr"] = $opts["attr"] ?? [];

                // Set default label
                if(!array_key_exists("label", $fieldOptions)) {
                    $label = explode("-", $formattedField);
                    $fieldOptions["label"] = $slotLabel ?? ucwords(str_replace("_", " ", BaseService::camelToSnakeCase(end($label))));
                }

                if($class == FileType::class || $class == ImageType::class || $class == AvatarType::class) {
                    $fieldOptions["max_filesize"] = $fieldOptions["max_filesize"] ?? Uploader::getMaxFilesize(SettingTranslation::class, "value");
                    $fieldOptions["mime_types"]   = $fieldOptions["mime_types"]   ?? Uploader::getMimeTypes(SettingTranslation::class, "value");
                    $fieldOptions["empty_data"]   = $slotName ?? "";
                }

                if(!array_key_exists("help", $fieldOptions))
                    $fieldOptions["help"] = $slotHelp ?? "";

                $fieldOptions["select2"] = true;
                $fieldOptions["multiple"] = false;
                $fieldOptions["data_class"] = Widget::class;
                
                $form->add($formattedField, $class, $fieldOptions);
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
        dump($newViewData);
        foreach($newViewData as $field => $value) {
            dump($value, $field);
            if($field == "valid") continue;

            // $formattedField = str_replace(".", "-", $field);
            // $newViewData[$field] = $newViewData[$field]->setValue($children[$formattedField]->getViewData() ?? "");
            dump($value);
        }

        $viewData = $newViewData;
    }
}
