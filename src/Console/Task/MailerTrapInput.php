<?php

namespace Base\Console\Task;

/** base:mailer:trap's arguments and options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class MailerTrapInput
{
    /**
     * @param string  $action status, on (arm), off (disarm), list, show, purge, release
     * @param ?string $id     the held message, for show, purge and release
     * @param bool    $all    every held message
     * @param ?string $reason why the trap was armed, for whoever finds it later
     */
    public function __construct(public string $action = 'status', public ?string $id = null, public bool $all = false, public ?string $reason = null)
    {
    }
}
