<?php

namespace Base\Service;

use Base\BaseBundle;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Slot;
use Base\Repository\Layout\Widget\SlotRepository as WidgetSlotRepository;
use Base\Repository\Layout\WidgetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

class WidgetProvider implements WidgetProviderInterface
{
    public function __construct(WidgetRepository $widgetRepository, WidgetSlotRepository $widgetSlotRepository)
    {
        $this->widgetRepository = $widgetRepository;
        $this->widgetSlotRepository = $widgetSlotRepository;
    }

    protected $widgets = [];
    public function get(string $uuid, bool $useCache = BaseBundle::CACHE): ?Widget { return $this->getWidget($uuid, $useCache); }
    public function getWidget(string $uuid, bool $useCache = BaseBundle::CACHE): ?Widget 
    { 
        $fn = $useCache && !is_cli() ? "cacheOneByPath" : "findOneByPath";
        return $this->widgetRepository->$fn($uuid);
    }

    public function all(bool $useCache = BaseBundle::CACHE): array 
    {
        $fn = $useCache && !is_cli() ? "cacheAll" : "findAll";
        return $this->widgetRepository->$fn()->getResult();
    }
    public function allSlots(bool $useCache = BaseBundle::CACHE): array 
    { 
        $fn = $useCache && !is_cli() ? "cacheAll" : "findAll";
        return array_transforms(fn($k, $s):array => [$s->getPath(), $s], $this->widgetSlotRepository->$fn()->getResult());
    }
    
    public function getSlot(string $path): ?Slot { return $this->getWidgetSlot($path); }
    public function getWidgetSlot(string $path, bool $useCache = BaseBundle::CACHE): ?Slot
    {
        $fn = $useCache && !is_cli() ? "cacheOneByPath" : "findOneByPath";
        return $this->widgetSlotRepository->$fn($path);
    }


}