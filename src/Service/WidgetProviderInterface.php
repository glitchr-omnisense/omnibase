<?php

namespace Base\Service;

use Base\Entity\Sitemap\Widget;
use Base\Entity\Sitemap\WidgetSlot;

interface WidgetProviderInterface
{
    public function get(string $widgetName): ?Widget;
    public function getSlot(string $widgetSlotName): ?WidgetSlot;
}