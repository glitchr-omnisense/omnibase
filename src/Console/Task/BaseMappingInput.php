<?php

namespace Base\Console\Task;

/** base:mapping's options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class BaseMappingInput
{
    /** @param string $source the application's App\ folder (Symfony: ./src) */
    public function __construct(public string $source = 'src')
    {
    }
}
