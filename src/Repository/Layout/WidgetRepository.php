<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\Widget;

use Base\Database\Repository\ServiceEntityRepository;

/**
 * @method Widget|null find($id, $lockMode = null, $lockVersion = null)
 * @method Widget|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Widget[]    findAll()
 * @method Widget[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */

class WidgetRepository extends ServiceEntityRepository implements Widgets
{
    /** Widgets (step R28): WidgetProvider's finders, the result cache unless $cached is false. */
    public function oneByPath(string $path, bool $cached = true): ?Widget
    {
        return $cached ? $this->cacheOneByPath($path) : $this->findOneByPath($path);
    }

    public function every(bool $cached = true): array
    {
        return $cached ? $this->cacheAll()->getResult() : $this->findAll();
    }

}
