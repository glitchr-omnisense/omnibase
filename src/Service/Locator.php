<?php

namespace Base\Service;

use Base\Traits\BaseCommonTrait;
use Base\Traits\BaseTrait;

/**
 * Where BaseTrait's static accessors keep what they resolved (audit
 * "lesringostars face au master plan", step R28): the runtime locator
 * BaseBundle::boot() seeds, the project's folder, the environment, each
 * service once asked. Framework-free - a class of the Foundation -, so an
 * entity's or an attribute's accessor no longer loads BaseService, the
 * Symfony facade (a kernel, a form factory, a profiler in its constructor),
 * which held them before. BaseService extends it: the same statics, its
 * set*() calls and BaseService::getX() as before.
 */
class Locator
{
    use BaseTrait;
    use BaseCommonTrait;
}
