<?php

namespace Base\Twig\Extension;

use Base\Service\WidgetProvider;
use Base\Service\WidgetProviderInterface;

use Twig\Environment;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

/**
 * @author Marco Meyer <marco.meyerconde@gmail.com>
 *
 */

final class WidgetTwigExtension extends AbstractExtension
{
    /**
     * @var WidgetProvider
     */
    protected $widgetProvider;
    
    public function __construct(WidgetProviderInterface $widgetProvider) { $this->widgetProvider = $widgetProvider; }

    public function getName() { return 'widget_extension'; }
    public function getFunctions() : array
    {
        return [
            new TwigFunction("render_slot", [$this, 'render_slot'], ["needs_environment" => true, 'is_safe' => ['all']]),
            new TwigFunction("all_slots",   [WidgetProvider::class, 'allSlots'], ['is_safe' => ['all']]),
            new TwigFunction("all_widgets", [WidgetProvider::class, 'all'], ['is_safe' => ['all']])
        ];
    }

    function render_slot(Environment $twig, string $slot, array $options = [], ?string $template = null): string
    {
        $widgetSlot = $this->widgetProvider->getSlot($slot);
        if(!$widgetSlot) return "";

        $widget = $widgetSlot->getWidget();
        if(!$widget) return "";

        $options["widget"]   = $widget;

        $widget->setTemplate($template);
        $entityClass = camel2snake(class_basename($widget), "-");
        $templateClass = camel2snake(str_strip(basename($widget->getTemplate()), "", ".html.twig"), "-");

        $options["row_attr"] = $options["row_attr"] ?? [];
        $options["row_attr"]["class"]  = $options["row_attr"]["class"] ?? "";
        $options["row_attr"]["class"] .= " widget-".$entityClass;
        $options["row_attr"]["class"] .= ($templateClass != $entityClass) ? " widget-".$templateClass : "";

        $options["attr"] = $options["attr"] ?? [];
        $options["attr"]["class"] = $options["attr"]["class"] ?? "";

        $options["label"] = $options["label"] ?? $widget->getTitle();

        return $twig->render($widget->getTemplate(), $options);
    }
}
