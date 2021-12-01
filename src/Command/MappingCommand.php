<?php

namespace Base\Command;

use Base\BaseBundle;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class MappingCommand extends Command
{
    protected static $defaultName = 'base:mapping';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface)
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');

        $output->getFormatter()->setStyle('info', new OutputFormatterStyle('green', null, ['bold']));
        $output->getFormatter()->setStyle('warning', new OutputFormatterStyle('yellow', null, ['bold']));
        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, ['bold']));
        $output->getFormatter()->setStyle('ln', new OutputFormatterStyle('cyan', null, ['bold']));

        $appList = BaseBundle::getAllClasses("./src", "App");
        $baseLocation = dirname((new \ReflectionClass('Base\\BaseBundle'))->getFileName());
        $baseList = array_merge(
            BaseBundle::getAllClasses($baseLocation."/Enum", "Base"),
            BaseBundle::getAllClasses($baseLocation."/Form", "Base"),
            BaseBundle::getAllClasses($baseLocation."/Entity", "Base"),
            BaseBundle::getAllClasses($baseLocation."/Repository", "Base")
        );

        $output->section()->writeln('');
        $output->section()->writeln('This command gives access to the mapping applied from \\Base to \\App namespace');
        $output->section()->writeln('');
        $output->section()->writeln('This is meant to avoid rewriting Base classes');
        $output->section()->writeln('and use customized \\App classes extending from Base classes');
        $output->section()->writeln('');

        $nAlias     = 0;
        $nException = 0;

        $output->section()->writeln('Complete mapping applied:');
        foreach ($baseList as $class) {

            $app  = "App\\$class";
            $base = "Base\\$class";
            if (!in_array($class, $appList)) {

                $output->section()->writeln(" * <warning>No application file found:</> <ln>$base</> aliased to <red>$app</> ");
                $nAlias++;

            } else {

                $output->section()->writeln(" * <info>Application file found:</> <red>$app</> (no alias)");
                $nException++;
            }
        }

        $output->section()->writeln('');
        $output->section()->writeln('Summary:');
        $output->section()->writeln('- ' . $nAlias . ' alias(es) from \\Base to \\App applied.');
        $output->section()->writeln('- There is/are '.$nException.' exception(s). Exception list below:');
        foreach ($appList as $class) {
            $app  = "App\\$class";
            $base = "Base\\$class";
            if (in_array($class, $baseList))
                $output->section()->writeln("  * <info>Application file found:</> <red>$app</> (no alias)");
        }


        return Command::SUCCESS;
    }
}
