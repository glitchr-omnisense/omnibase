<?php

namespace Base\View;

use Base\Service\WidgetProviderInterface;

/**
 * A widget slot of a page as data (plan "omnibase agnostic", step F4): the template of the widget
 * a slot holds and what it is rendered with (its row and widget classes, its label) -
 * WidgetTwigExtension's render_slot() renders it under Twig; Blade renders the same, a React page
 * takes it as props.
 */
final class Widgets
{
    public function __construct(private readonly WidgetProviderInterface $widgetProvider)
    {
    }

    /**
     * The slot's widget, its template and its options; null for a slot unknown or empty.
     *
     * @param array<string, mixed> $options
     *
     * @return array{template: string, options: array<string, mixed>}|null
     */
    public function slot(string $slot, array $options = [], ?string $template = null): ?array
    {
        $widget = $this->widgetProvider->getSlot($slot)?->getWidget();
        if (!$widget) {
            return null;
        }

        $options['widget'] = $widget;
        $template ??= $widget->getTemplate();

        $entityClass = camel2snake(class_name($widget), '-');
        $templateClass = camel2snake(str_strip(basename($template), '', '.html.twig'), '-');

        $options['row_attr'] = $options['row_attr'] ?? [];
        $options['row_attr']['class'] = $options['row_attr']['class'] ?? '';
        $options['row_attr']['class'] .= ' widget-'.$entityClass;
        $options['row_attr']['class'] .= ($templateClass != $entityClass) ? ' widget-'.$templateClass : '';

        $options['attr'] = $options['attr'] ?? [];
        $options['attr']['class'] = $options['attr']['class'] ?? '';
        $options['label'] = $options['label'] ?? $widget->getTitle();

        return ['template' => $template, 'options' => $options];
    }
}
