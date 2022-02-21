<?php

namespace Base\Command;

use Base\BaseBundle;
use Base\Component\Console\Command\Command;
use Base\Service\BaseService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseMappingCommand extends Command
{
    protected static $defaultName = 'base:mapping';

    protected static $defaultDescription = "
        This command gives access to the mapping applied from \\Base to \\App namespace'
        
        This is meant to avoid rewriting Base classes
        and use customized \\App classes extending from Base classes"; 

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $appList = BaseBundle::getAllClasses("./src", "App");
        $baseLocation = dirname((new \ReflectionClass('Base\\BaseBundle'))->getFileName());
        $baseList = array_merge(
            BaseBundle::getAllClasses($baseLocation."/Enum", "Base"),
            BaseBundle::getAllClasses($baseLocation."/Form", "Base"),
            BaseBundle::getAllClasses($baseLocation."/Entity", "Base"),
            BaseBundle::getAllClasses($baseLocation."/Repository", "Base")
        );

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
        $output->section()->writeln('- There is/are '.$nException.' overriding exception(s). Exception list below:');
        foreach ($appList as $class) {
            $app  = "App\\$class";
            $base = "Base\\$class";
            if (in_array($class, $baseList))
                $output->section()->writeln("  * <info>Application file found:</> <red>$app</> (no alias)");
        }


        return Command::SUCCESS;
    }
}
