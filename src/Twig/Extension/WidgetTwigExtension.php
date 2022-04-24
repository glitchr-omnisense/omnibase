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
    protected string $projectDir;
    public function __construct(Environment $twig, WidgetProviderInterface $widgetProvider)
    {
        $this->widgetProvider = $widgetProvider;
        $this->twig = $twig;
    }

    public function getFunctions() : array
    {
        return [
            new TwigFunction("render_widget",     [$this, 'render_widget'], ['is_safe' => ['all']]),
            new TwigFunction("all_slots",   [WidgetProvider::class, 'allSlots'], ['is_safe' => ['all']]),
            new TwigFunction("all_widgets", [WidgetProvider::class, 'all'], ['is_safe' => ['all']])
        ];
    }

    function render_widget(string $slot, array $widgetOptions = [], ?string $template = null): string
    {
        $widgetSlot = $this->widgetProvider->getSlot($slot);
        if(!$widgetSlot) return "";

        $widget = $widgetSlot->getWidget();
        if(!$widget) return "";
        
        $widgetOptions["widget"]   = $widget;
        
        $widget->setTemplate($template);
        $entityClass = camel2snake(class_basename($widget), "-");
        $templateClass = camel2snake(str_strip(basename($widget->getTemplate()), "", ".html.twig"), "-");
        
        $widgetOptions["row_attr"] = $widgetOptions["row_attr"] ?? [];
        $widgetOptions["row_attr"]["class"]  = $widgetOptions["row_attr"]["class"] ?? "";
        $widgetOptions["row_attr"]["class"] .= " widget-".$entityClass;
        $widgetOptions["row_attr"]["class"] .= ($templateClass != $entityClass) ? " widget-".$templateClass : "";

        $widgetOptions["attr"] = $widgetOptions["attr"] ?? [];
        $widgetOptions["attr"]["class"] = $widgetOptions["attr"]["class"] ?? "";

        $widgetOptions["label"] = $widgetOptions["label"] ?? $widget->getTitle();

        return $this->twig->render($widget->getTemplate(), $widgetOptions);
    }

}
