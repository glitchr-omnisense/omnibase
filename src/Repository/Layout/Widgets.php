<?php

namespace Base\Repository\Layout;

use Base\Database\Repository\Entities;
use Base\Entity\Layout\Widget;

/**
 * The widgets by their paths (audit "lesringostars face au master plan",
 * step R28; see Base\Database\Repository\Entities): what WidgetProvider asks,
 * through the finder's result cache unless told otherwise.
 *
 * @extends Entities<Widget>
 */
interface Widgets extends Entities
{
    public function oneByPath(string $path, bool $cached = true): ?Widget;

    /** @return list<Widget> */
    public function every(bool $cached = true): array;
}
