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
            new TwigFunction("render_widget", [$this, 'render_widget'], ['needs_context' => true])
        ];
    }

    function render_widget(array $context, string $slotName, array $widgetOptions = []): string
    {
        $widgetSlot = $this->widgetProvider->getWidgetSlot($slotName);
        if(!$widgetSlot) return "";

        $widget = $widgetSlot->getWidget();
        if(!$widget) return "";

        $widgetOptions["class"]      = $widgetOptions["class"]      ?? $widget->getOptions()["class"]      ?? null;
        $widgetOptions["class_item"] = $widgetOptions["class_item"] ?? $widget->getOptions()["class_item"] ?? null;

        return $this->twig->render($widget->getTemplate(), $widgetOptions);
    }

}
