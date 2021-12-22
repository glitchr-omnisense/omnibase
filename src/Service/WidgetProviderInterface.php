<?php

namespace Base\Service;

use Base\Entity\Sitemap\Widget;
use Base\Entity\Sitemap\Widget\Slot;

interface WidgetProviderInterface
{
    public function get(string $widgetName): ?Widget;
    public function getSlot(string $widgetSlotName): ?Slot;
}