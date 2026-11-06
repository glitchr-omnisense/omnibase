<?php

namespace Base\Console\Task;

/** icon:enums' options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class IconEnumsInput
{
    /**
     * @param string $source the application's App\ folder (Symfony: ./src)
     * @param string $enum   only the enumerations whose name starts so
     */
    public function __construct(public string $source = 'src', public string $enum = '')
    {
    }
}
