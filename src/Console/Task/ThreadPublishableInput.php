<?php

namespace Base\Console\Task;

/** thread:publishable's options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class ThreadPublishableInput
{
    /** @param bool $publish publish the threads whose time has come (listed only otherwise) */
    public function __construct(public bool $publish = false)
    {
    }
}
