<?php

namespace Base\Twig\Extension;

use Base\Service\WidgetProvider;
use Base\Service\WidgetProviderInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Base\View\Widgets;

/**
 * @author Marco Meyer <marco.meyerconde@gmail.com>
 */
final class WidgetTwigExtension extends AbstractExtension
{
    /**
     * @var WidgetProvider
     */
    protected $widgetProvider;

    public function __construct(WidgetProviderInterface $widgetProvider)
    {
        $this->widgetProvider = $widgetProvider;
        $this->widgets = new Widgets($widgetProvider);
    }

    private readonly Widgets $widgets;

    /**
     * @return string
     */
    public function getName()
    {
        return 'widget_extension';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_slot', [$this, 'render_slot'], ['needs_environment' => true, 'is_safe' => ['all']]),
            new TwigFunction('all_slots', [WidgetProvider::class, 'allSlots'], ['is_safe' => ['all']]),
            new TwigFunction('all_widgets', [WidgetProvider::class, 'all'], ['is_safe' => ['all']]),
        ];
    }

    /** The widget a slot holds, rendered with what Base\View\Widgets gives it; nothing for an empty slot. */
    public function render_slot(Environment $twig, string $slot, array $options = [], ?string $template = null): string
    {
        $widget = $this->widgets->slot($slot, $options, $template);

        return null === $widget ? '' : $twig->render($widget['template'], $widget['options']);
    }
}
