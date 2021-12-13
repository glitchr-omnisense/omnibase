<?php

namespace Base\Form\Type\Sitemap;

use Base\Entity\Sitemap\Widget;
use Base\Field\Type\EntityType;
use Base\Field\Type\SelectType;
use Base\Service\BaseService;
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
            'widgets' => [],
            'sortable' => true,
            'multiple' => false,
            'excluded_widgets' => [],
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
            $data = $event->getData();

            $widgetSlots = [];
            
            $formattedWidgets = $this->getFormattedData($options["widgets"]);
            foreach($formattedWidgets as $formattedWidget => $widgetOptions) {

                $widget = str_replace("-", ".", $formattedWidget);
                $widgetSlots[$formattedWidget] = $this->widgetProvider->getWidgetSlot($widget);
            }

            foreach($widgetSlots as $formattedWidget => $slot) {

                $slotLabel = ($slot ? $slot->getLabel() : null);
                $slotHelp  = ($slot ? $slot->getHelp()  : null);

                // Exclude requested widgets
                $widget = str_replace("-", ".", $formattedWidget);
                if(in_array($widget, $options["excluded_widgets"])) 
                    continue;

                // Set widget options
                $widgetOptions = $options["widgets"][$widget];
                $widgetOptions["attr"] = $opts["attr"] ?? [];
                $widgetOptions["multiple"] = $options["widgets"][$widget]["multiple"] ?? false;

                $widgetOptions["data_class"] = null;
                $widgetOptions["required"]   = $options["widgets"][$widget]["required"] ?? false;
                $widgetOptions["select2"]    = $options["select2"] ?? [];

                $widgetOptions["choices"] = $data ?? [];
                $widgetOptions["choice_filter"] =  $options["widgets"][$widget]["choice_filter"] ?? null;
                $widgetOptions["choice_filter"] = is_array($widgetOptions["choice_filter"]) ? 
                function ($widgets) use ($widgetOptions) {
                    if( !is_object($widgets) ) return true;
                    return $widgets !== null && in_array(get_class($widgets), $widgetOptions["choice_filter"], true);
                } : $widgetOptions["choice_filter"];

                // Set default label
                if(!array_key_exists("label", $widgetOptions)) {
                    $label = explode("-", $formattedWidget);
                    $widgetOptions["label"] = $slotLabel ?? ucwords(str_replace("_", " ", camel_to_snake(end($label))));
                }

                if(!array_key_exists("help", $widgetOptions))
                    $widgetOptions["help"] = $slotHelp ?? "";

                $widgets = $slot ? $slot->getWidgets()->toArray() : [];
                $form->add($formattedWidget, SelectType::class, $widgetOptions);
                $form->get($formattedWidget)->setData($widgetOptions["multiple"] ? array_map(fn($w) => strval($w), $widgets) : strval($widgets[0] ?? null));
            }

            if(count($options["widgets"]) > 0)
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
        
        foreach($newViewData as $widget => $value) {
            
            if($widget == "valid") continue;

            $formattedWidget = str_replace(".", "-", $widget);

            $newViewData[$widget] = $children[$formattedWidget]->getData() ?? [];
            if(!is_array($newViewData[$widget])) 
                $newViewData[$widget] = [$newViewData[$widget]];
        }

        unset($newViewData["valid"]);
        $viewData = $newViewData;
    }
}
