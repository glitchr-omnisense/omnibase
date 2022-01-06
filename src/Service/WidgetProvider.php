<?php

namespace Base\Service;

use Base\Entity\Sitemap\Widget;
use Base\Entity\Sitemap\Widget\Slot;
use Base\Repository\Sitemap\Widget\SlotRepository as WidgetSlotRepository;
use Base\Repository\Sitemap\WidgetRepository;

class WidgetProvider implements WidgetProviderInterface
{
    public function __construct(WidgetRepository $widgetRepository, WidgetSlotRepository $widgetSlotRepository)
    {
        $this->widgetRepository = $widgetRepository;
        $this->widgetSlotRepository = $widgetSlotRepository;
    }

    protected $uuidByPath = [];
    public function getUuidByPath(string $path) { return $this->uuidByPath[$path] ?? null; }
    
    protected $widgets = [];
    public function get(string $uuid): ?Widget { return $this->getWidget($uuid); }
    public function getWidget(string $uuid): ?Widget
    {
        $this->widgets[$uuid] = $this->widgets[$uuid] ?? $this->widgetRepository->findOneByUuid($uuid);
        return $this->widgets[$uuid];
    }

    public function getSlot(string $path): ?Slot { return $this->getWidgetSlot($path); }
    public function getWidgetSlot(string $path): ?Slot
    {
        $slot = null;
        if($this->uuidByPath[$path] ?? null)
            $slot = $this->widgets[$this->uuidByPath[$path]] ?? null;

        $slot = $slot ?? $this->widgetSlotRepository->findOneByPath($path);
        $this->uuidByPath[$path] = $slot ? $slot->getUuid() : null;
        $uuid = $this->uuidByPath[$path] ?? null;

        $this->widgets[$uuid] = $slot;
        return $this->widgets[$uuid];
    }

}