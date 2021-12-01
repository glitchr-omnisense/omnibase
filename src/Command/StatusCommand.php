<?php

namespace Base\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class StatusCommand extends Command
{
    protected static $defaultName = 'base:status';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface)
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');

        $output->section()->writeln('Hello World!');

        return Command::SUCCESS;
    }
}
