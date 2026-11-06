<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Database\Type\EnumType;
use Base\Service\Model\ClassFinder;

/**
 * translation:enums (plan "omnibase agnostic", step F5e, slice 3): the application's enumerations, then the core's
 * (the native PHP enums and the groups without values of their own left out) and, for each language, the key of
 * their noun, "@enums.<the path>.<suffix>", then of each value's, "@enums.<the path>.<value>.<suffix>", with
 * their translations or "no translation found".
 */
final class TranslationEnums extends TranslationTask
{
    public function run(TranslationEnumsInput $input, ?\Closure $sink = null): Report
    {
        $report = self::report($sink);
        $enums = array_merge(ClassFinder::listed($input->source.'/Enum'), ClassFinder::listed(self::core().'/Enum'));

        $maxLength = 0;
        if (!$input->enum) {
            foreach ($enums as $enum) {
                $maxLength = max(\strlen($enum), $maxLength);
            }
        }

        [$locale, $availableLocales] = $this->languages($input->locale);

        if ($enums) {
            $report->line('Enum list: '.$input->enum);
        }
        foreach ($enums as $enum) {
            if (!str_starts_with($enum, $input->enum) || !is_subclass_of($enum, EnumType::class)) {
                continue; // a native PHP enum (CommentState...): no permitted values to list
            }
            try {
                $values = $enum::getPermittedValues(false);
            } catch (\Exception) {
                continue; // a group of enums (SystemOfUnits\...), no value of its own
            }

            $path = camel2snake(implode('.', tail(explode('\\', $enum), 2)));

            $trans = '';
            $space = '';
            foreach ($availableLocales as $currentLocale) {
                if (null !== $locale && $locale != $currentLocale) {
                    continue;
                }
                $prefix = null === $locale ? "\n\t - " : '';
                $space = null === $locale ? '' : str_repeat(' ', max($maxLength - \strlen($enum), 0));

                $trans .= $this->key('@enums.'.$path.'.'.$input->suffix, $prefix."@enums[$currentLocale].<ln>".$path.'.'.$input->suffix.'</ln>', $currentLocale);
            }

            $report->line("\n * <magenta>".trim($enum).'</magenta> '.$space.": $trans");

            $maxValueLength = 0;
            foreach ($values as $value) {
                $maxValueLength = max(\strlen($enum.'::'.$value), $maxValueLength);
            }

            foreach ($values as $value) {
                $value = (string) $value;
                $translatedValue = '';
                foreach ($availableLocales as $currentLocale) {
                    if (null !== $locale && $locale != $currentLocale) {
                        continue;
                    }
                    $prefix = null === $locale ? "\n\t\t - " : '';
                    $space = null === $locale ? '' : str_repeat(' ', max($maxValueLength - \strlen($enum.'::'.$value), 0));

                    $key = $path.'.'.strtolower($value).'.'.$input->suffix;
                    $translatedValue .= $this->key('@enums.'.$key, $prefix."@enums[$currentLocale].<ln>".$key.'</ln>', $currentLocale);
                }
                $report->line("\t * <info>".trim($enum.'::'.$value).'</info> '.$space.": $translatedValue");
            }
        }

        return $report;
    }
}
