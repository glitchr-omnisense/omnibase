<?php

namespace Base\Console\Task;

/** notifier:mail:test's options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class NotifierMailTestInput
{
    /** @param ?string $user the account mailed (its id, e-mail address or username); the technical recipient's otherwise */
    public function __construct(public ?string $user = null)
    {
    }
}
