<?php

namespace Base\Repository\Layout;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Layout\TextOverride;

/**
 * @method TextOverride|null find($id)
 * @method TextOverride[]    findAll()
 */
class TextOverrideRepository extends ServiceEntityRepository
{
    /** @return array<string, string> every rewritten text, by "domain|locale|key" */
    public function map(): array
    {
        $map = [];
        foreach ($this->findAll() as $override) {
            $map[$override->getDomain().'|'.$override->getLocale().'|'.$override->getKey()] = $override->getValue();
        }

        return $map;
    }
}
