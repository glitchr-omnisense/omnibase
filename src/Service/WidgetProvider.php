<?php

namespace Base\Service;

use App\Entity\User;
use Base\Entity\Sitemap\Widget;
use Base\Entity\Sitemap\WidgetSlot;
use Base\Exception\MissingLocaleException;
use Base\Repository\Sitemap\WidgetRepository;
use Base\Repository\Sitemap\WidgetSlotRepository;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Locale;
use Symfony\Component\Intl\Locales;
use Symfony\Contracts\Translation\TranslatorInterface;

class WidgetProvider implements WidgetProviderInterface
{
    public const SEPARATOR = "-";

    /**
     * @var TranslatorInterface
     */
    protected $translator = null;
    
    public function __construct(TranslatorInterface $translator, WidgetRepository $widgetRepository, WidgetSlotRepository $widgetSlotRepository)
    {
        $this->translator   = $translator;
        $this->widgetRepository = $widgetRepository;
        $this->widgetSlotRepository = $widgetSlotRepository;
    }

    public function get(string $slug): ?Widget
    {
        return $this->widgetRepository->findOneBySlug($slug);
    }

    public function getSlot(string $name): ?WidgetSlot
    {
        return $this->widgetSlotRepository->findOneByName($name);
    }

}