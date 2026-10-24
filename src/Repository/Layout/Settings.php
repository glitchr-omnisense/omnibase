<?php

namespace Base\Repository\Layout;

use Base\Database\Repository\Entities;
use Base\Entity\Layout\Setting;

/**
 * The settings stored in the database (audit "lesringostars face au master
 * plan", step R15; see Base\Database\Repository\Entities), with what
 * SettingBag reads through the finder (step R28).
 *
 * @extends Entities<Setting>
 */
interface Settings extends Entities
{
    /**
     * The settings whose path starts with $path (every setting when null),
     * only those linked to a bag when $linkedBagOnly, through the finder's
     * cache unless $cached is false (step R28: SettingBag's finders).
     *
     * @return list<Setting>
     */
    public function under(?string $path, bool $linkedBagOnly = false, bool $cached = true): array;

    /** The setting of this path, compared case-insensitively. */
    public function oneByInsensitivePath(string $path): ?Setting;
}
