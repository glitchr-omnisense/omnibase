<?php

namespace Base\Console\Task;

/** app:notifier's options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class NotifierOverviewInput
{
    /** @param class-string $notifier the application's notifier (App\Notifier\Notifier, the core's where it has none) */
    public function __construct(public string $notifier = 'App\Notifier\Notifier')
    {
    }
}
