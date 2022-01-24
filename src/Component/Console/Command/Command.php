<?php

namespace Base\Component\Console\Command;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class Command extends SymfonyCommand
{
    public function run(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface)
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');

        $output->getFormatter()->setStyle('info', new OutputFormatterStyle('green', null, ['bold']));
        $output->getFormatter()->setStyle('warning', new OutputFormatterStyle('yellow', null, ['bold']));
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, ['bold']));
        $output->getFormatter()->setStyle('ln', new OutputFormatterStyle('cyan', null, ['bold']));
        $output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, ['bold']));

        $defaultDescription = get_called_class()::$defaultDescription;
        if($defaultDescription) {
            
            $output->section()->writeln("\n // Command purpose :");
            foreach(explode("\n", $defaultDescription) as $line)
                $output->section()->writeln(" // \t".trim($line));
    
            $output->section()->writeln(" // \n");
        }

        return parent::run($input, $output);
    }
}
