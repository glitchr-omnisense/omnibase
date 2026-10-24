<?php

namespace Base\Service;

use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Slot;
use Base\Repository\Layout\Widget\Slots;
use Base\Repository\Layout\Widgets;

/**
 * The widgets and their slots by path. Through their interfaces (audit
 * "lesringostars face au master plan", step R28), where it asked the entity
 * manager for their repositories: the same finders, the result cache unless
 * $useCache is false.
 */
class WidgetProvider implements WidgetProviderInterface
{
    public function __construct(protected Widgets $widgets, protected Slots $slots)
    {
    }

    public function get(string $widgetName, bool $useCache = true): ?Widget
    {
        return $this->getWidget($widgetName, $useCache);
    }

    public function getWidget(string $widgetName, bool $useCache = true): ?Widget
    {
        return $this->widgets->oneByPath($widgetName, $useCache);
    }

    public function all(bool $useCache = true): array
    {
        return $this->widgets->every($useCache);
    }

    public function allSlots(bool $useCache = true): array
    {
        return array_transforms(fn($k, $s): array => [$s->getPath(), $s], $this->slots->every($useCache));
    }

    public function getSlot(string $widgetSlotName, bool $useCache = true): ?Slot
    {
        return $this->getWidgetSlot($widgetSlotName, $useCache);
    }

    public function getWidgetSlot(string $path, bool $useCache = true): ?Slot
    {
        return $this->slots->oneByPath($path, $useCache);
    }
}
