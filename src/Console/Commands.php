<?php

namespace Base\Console;

/**
 * The host's other console commands, for a task that runs some (plan "omnibase agnostic", step F5e, slice 3:
 * demo:reset runs the fixtures, uploader:entities, the cache pools). Each host answers the names it has; a name
 * it has not got answers null. Their output goes where the host prints, not to the task's Report.
 */
interface Commands
{
    /**
     * @param array<string, mixed> $arguments the options by name ('--all' => true)
     *
     * @return ?int the command's status, null when the host has no such command
     */
    public function run(string $name, array $arguments = []): ?int;
}
