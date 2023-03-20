<?php

namespace Base\Console;

interface ConsoleInterface
{
    public function exec(string $command, array $parameters = []): ?string;
}
