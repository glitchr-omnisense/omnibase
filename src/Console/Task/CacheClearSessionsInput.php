<?php

namespace Base\Console\Task;

/** cache:clear:sessions' input (plan "omnibase agnostic", step F5e, slice 3): where the host keeps its session files. */
final readonly class CacheClearSessionsInput
{
    /** @param string $pattern the session files' names (Symfony's native handler: sess_*) */
    public function __construct(
        public string $directory,
        public string $pattern = 'sess_*',
    ) {
    }
}
