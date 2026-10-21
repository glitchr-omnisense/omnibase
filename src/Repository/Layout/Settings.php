<?php

namespace Base\Repository\Layout;

use Base\Database\Repository\Entities;
use Base\Entity\Layout\Setting;

/**
 * The settings stored in the database (audit "lesringostars face au master
 * plan", step R15; see Base\Database\Repository\Entities). What SettingBag
 * reads through the finder stays its own for now.
 *
 * @extends Entities<Setting>
 */
interface Settings extends Entities
{
}
