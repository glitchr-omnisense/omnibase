<?php

namespace Base\Repository\Layout\Widget;

use Base\Entity\Layout\Widget\Slot;
use Base\Repository\Layout\WidgetRepository;
use Base\Repository\Layout\Widget\Slots;

/**
 * @method Slot|null find($id)
 * @method Slot|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Slot[]    findAll()
 * @method Slot[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class SlotRepository extends WidgetRepository implements Slots
{
    /** Slots (step R28): WidgetProvider's finders, the result cache unless $cached is false. */
    public function oneByPath(string $path, bool $cached = true): ?Slot
    {
        return $cached ? $this->cacheOneByPath($path) : $this->findOneByPath($path);
    }
}
