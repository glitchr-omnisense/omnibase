<?php

namespace Base\Service;

use Base\Entity\Sitemap\Widget;
use Base\Entity\Sitemap\WidgetSlot;
use Base\Repository\Sitemap\WidgetRepository;
use Base\Repository\Sitemap\WidgetSlotRepository;

class WidgetProvider implements WidgetProviderInterface
{
    public function __construct(WidgetRepository $widgetRepository, WidgetSlotRepository $widgetSlotRepository)
    {
        $this->widgetRepository = $widgetRepository;
        $this->widgetSlotRepository = $widgetSlotRepository;
    }

    protected $widgets = [];
    public function get(string $slug): ?Widget { return $this->getWidget($slug); }
    public function getWidget(string $slug): ?Widget
    {
        $widgets = $widgets ?? $this->widgetRepository->findOneBySlug($slug);
        return $widgets[$slug];
    }

    protected $widgetSlots = [];
    public function getSlot(string $name): ?WidgetSlot { return $this->getWidgetSlot($name); }
    public function getWidgetSlot(string $name): ?WidgetSlot
    {
        $widgetSlots[$name] = $widgetSlots[$name] ?? $this->widgetSlotRepository->findOneByName($name);
        return $widgetSlots[$name];
    }
}