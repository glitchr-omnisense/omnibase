<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Database\Type\EnumType;

/**
 * icon:enums (plan "omnibase agnostic", step F5e, slice 3): the core's enumerations, then the application's, and
 * each value's icons (#[Iconize]); the native PHP enums and the groups without values of their own left out.
 */
final class IconEnums
{
    public function run(IconEnumsInput $input, ?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        $enums = array_merge(Folders::classes(Folders::core().'/Enum'), Folders::classes($input->source.'/Enum'));

        if ($enums) {
            $report->line('Enum list: '.$input->enum);
        }
        foreach ($enums as $enum) {
            if (!str_starts_with($enum, $input->enum)) {
                continue;
            }
            if (!is_subclass_of($enum, EnumType::class) || !method_exists($enum, '__iconizeStatic')) {
                continue; // a native PHP enum (CommentState...): no permitted values to list
            }
            try {
                $enum::getPermittedValues(false);
            } catch (\Exception) {
                continue; // a group of enums (SystemOfUnits\...), no value of its own
            }

            $report->line(' * <info>'.$enum.'</info>');

            $iconize = $enum::__iconizeStatic();
            $permittedValues = $enum::getPermittedValues(false);

            $maxLength = 0;
            foreach ($permittedValues as $value) {
                $maxLength = max($maxLength, \strlen($value));
            }

            foreach ($permittedValues as $value) {
                $space = str_repeat(' ', max($maxLength - \strlen($value), 0));
                $icons = \is_array($iconize[$value]) ? $iconize[$value] : [$iconize[$value]];
                $report->line("\t<warning>".$value.'</warning> '.$space.': ['.implode(',', $icons).']');
            }
        }

        return $report;
    }
}
