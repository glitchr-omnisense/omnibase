<?php

namespace Base\Twig\Extension;

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
            new TwigFunction("render_widget", [$this, 'render_widget'])
        ];
    }

    function render_widget(string $slot, int $position = 0, array $widgetOptions = []): string
    {
        $widgetSlot = $this->widgetProvider->getSlot($slot);
        
        if(!$widgetSlot) return "";

        $widgets = $widgetSlot->getWidgets();
        $widget  = $widgets->containsKey($position) ? $widgets->get($position) : null;
        if(!$widget) return "";

        $widgetOptions["class"]      = $widgetOptions["class"]      ?? $widgetSlot->getAttribute("class");
        $widgetOptions["class_item"] = $widgetOptions["class_item"] ?? $widgetSlot->getAttribute("class_item");
        $widgetOptions["widget"] = $widget;

        return $this->twig->render($widget->getTemplate(), $widgetOptions);
    }

}
