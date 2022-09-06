<?php

namespace Base\Service;

use Base\BaseBundle;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Slot;
use Doctrine\ORM\EntityManagerInterface;

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
    public function get(string $uuid, bool $useCache = BaseBundle::CACHE): ?Widget { return $this->getWidget($uuid, $useCache); }
    public function getWidget(string $uuid, bool $useCache = BaseBundle::CACHE): ?Widget
    {
        $fn = $useCache ? "cacheOneEagerlyByPath" : "findOneEagerlyByPath";
        return $this->widgetRepository ? $this->widgetRepository->$fn([], $uuid) : null;
    }

    public function all(bool $useCache = BaseBundle::CACHE): array
    {
        $fn = $useCache ? "cacheAllEagerly" : "findAllEagerly";
        return $this->widgetRepository ? $this->widgetRepository->$fn([])->getResult() : [];
    }
    public function allSlots(bool $useCache = BaseBundle::CACHE): array
    {
        $fn = $useCache ? "cacheAllEagerly" : "findAllEagerly";
        return array_transforms(fn($k, $s):array => [$s->getPath(), $s], $this->widgetSlotRepository->$fn(["widget"])->getResult());
    }

    public function getSlot(string $path, bool $useCache = BaseBundle::CACHE): ?Slot { return $this->getWidgetSlot($path, $useCache); }
    public function getWidgetSlot(string $path, bool $useCache = BaseBundle::CACHE): ?Slot
    {
        $fn = $useCache ? "cacheOneEagerlyByPath" : "findOneEagerlyByPath";
        return $this->widgetSlotRepository ? $this->widgetSlotRepository->$fn(["widget"], $path) : null;
    }
}
