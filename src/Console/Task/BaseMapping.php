<?php

namespace Base\Console\Task;

use Base\Console\Report;

/**
 * base:mapping (plan "omnibase agnostic", step F5e, slice 3): the core's enumerations, forms, entities and
 * repositories, each aliased to the App\ class of the same name where the application has none (the App\
 * convention), and the application's classes that take their place.
 */
final class BaseMapping
{
    /** The core's folders whose classes the App\ convention aliases. */
    public const FOLDERS = ['Enum', 'Form', 'Entity', 'Repository'];

    public function run(BaseMappingInput $input, ?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        $appList = Folders::classes($input->source, 'App');
        $baseList = [];
        foreach (self::FOLDERS as $folder) {
            $baseList = array_merge($baseList, Folders::classes(Folders::core().'/'.$folder, 'Base'));
        }

        $nAlias = 0;
        $nException = 0;

        $report->line('Complete mapping applied:');
        foreach ($baseList as $class) {
            $app = "App\\$class";
            $base = "Base\\$class";
            if (!\in_array($class, $appList)) {
                $report->line(" * <warning>No application file found:</> <ln>$base</> aliased to <red>$app</> ");
                ++$nAlias;
            } else {
                $report->line(" * <info>Application file found:</> <red>$app</> (no alias)");
                ++$nException;
            }
        }

        $report->line('');
        $report->line('Summary:');
        $report->line('- '.$nAlias.' alias(es) from \\Base to \\App applied.');
        $report->line('- There is/are '.$nException.' overriding exception(s). Exception list below:');
        foreach ($appList as $class) {
            if (\in_array($class, $baseList)) {
                $report->line("  * <info>Application file found:</> <red>App\\$class</> (no alias)");
            }
        }

        return $report;
    }
}
