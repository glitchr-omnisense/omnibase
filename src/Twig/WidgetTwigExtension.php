<?php

namespace Base\Twig;

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
    public function __construct(WidgetProviderInterface $widgetProvider)
    {
        $this->widgetProvider = $widgetProvider;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction("render_widget", [$this, 'render_widget'], ['needs_context' => true])
        ];
    }

    function render_widget(array $context, string $slotName): string
    {
        $slot = $this->widgetProvider->getSlot($slotName);

        $isDebug = $context["app"]->getDebug();
        // if($isDebug && !$slot)
        //     throw new Exception("Widget slot \"".$slotName."\" not referenced in database.");

        return "render_widget(".$slotName.")";
    }

}
