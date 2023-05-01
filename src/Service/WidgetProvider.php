<?php

namespace Base\Service;

use Base\BaseBundle;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Slot;
use Doctrine\ORM\EntityManagerInterface;

/**
 *
 */
class WidgetProvider implements WidgetProviderInterface
{
    protected $widgetRepository;
    protected $widgetSlotRepository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->widgetRepository = $entityManager->getRepository(Widget::class);
        $this->widgetSlotRepository = $entityManager->getRepository(Slot::class);
    }

    protected $widgets = [];

    public function get(string $widgetName, bool $useCache = BaseBundle::USE_CACHE): ?Widget
    {
        return $this->getWidget($widgetName, $useCache);
    }

    public function getWidget(string $widgetName, bool $useCache = BaseBundle::USE_CACHE): ?Widget
    {
        $fn = $useCache ? "cacheOneByPath" : "findOneByPath";
        return $this->widgetRepository?->$fn($widgetName);
    }

    public function all(bool $useCache = BaseBundle::USE_CACHE): array
    {
        $fn = $useCache ? "cacheAll" : "findAll";
        return $this->widgetRepository ? $this->widgetRepository->$fn()->getResult() : [];
    }

    public function allSlots(bool $useCache = BaseBundle::USE_CACHE): array
    {
        $fn = $useCache ? "cacheAll" : "findAll";
        return array_transforms(fn($k, $s): array => [$s->getPath(), $s], $this->widgetSlotRepository->$fn()->getResult());
    }

    public function getSlot(string $widgetSlotName, bool $useCache = BaseBundle::USE_CACHE): ?Slot
    {
        return $this->getWidgetSlot($widgetSlotName, $useCache);
    }

    public function getWidgetSlot(string $path, bool $useCache = BaseBundle::USE_CACHE): ?Slot
    {
        $fn = $useCache ? "cacheOneByPath" : "findOneByPath";
        return $this->widgetSlotRepository?->$fn($path);
    }
}
