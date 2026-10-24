<?php

namespace Base\Repository\Layout\Widget;

use Base\Database\Repository\Entities;
use Base\Entity\Layout\Widget\Slot;

/**
 * The widgets' slots by their paths (audit "lesringostars face au master
 * plan", step R28; see Base\Database\Repository\Entities): what
 * WidgetProvider asks, through the finder's result cache unless told otherwise.
 *
 * @extends Entities<Slot>
 */
interface Slots extends Entities
{
    public function oneByPath(string $path, bool $cached = true): ?Slot;

    /** @return list<Slot> */
    public function every(bool $cached = true): array;
}
