<?php

namespace Base\Twig\Extension;

use Base\Service\BaseService;
use Base\Controller\BaseController;
use Base\Entity\User\Notification;
use Base\Service\WidgetProviderInterface;
use Exception;
use Symfony\Bridge\Twig\Mime\WrappedTemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

use Twig\Environment;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @author Marco Meyer <marco.meyerconde@gmail.com>
 *
 */

use Twig\Extra\Intl\IntlExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class WidgetTwigExtension extends AbstractExtension
{
    protected string $projectDir;
    public function __construct(Environment $twig, WidgetProviderInterface $widgetProvider)
    {
        $this->widgetProvider = $widgetProvider;
        $this->twig = $twig;
    }

    public function getFunctions()
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
