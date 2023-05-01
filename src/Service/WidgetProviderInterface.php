<?php

namespace Base\Service;

use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Slot;

/**
 *
 */
interface WidgetProviderInterface
{
    public function all(): array;

    public function allSlots(): array;

    public function get(string $widgetName): ?Widget;

    public function getSlot(string $widgetSlotName): ?Slot;
}
