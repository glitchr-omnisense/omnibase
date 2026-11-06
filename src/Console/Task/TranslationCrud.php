<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Service\Model\ClassFinder;
use Base\Service\Translator;

/**
 * translation:crud (plan "omnibase agnostic", step F5e, slice 3): the back office's CRUD controllers - the core's,
 * then the application's - and, for each language, the key of their page's title,
 * "@admin.crud.<the class's path>.<action>.<suffix>", with its translation or "no translation found".
 */
final class TranslationCrud extends TranslationTask
{
    public function run(TranslationCrudInput $input, ?\Closure $sink = null): Report
    {
        $report = self::report($sink);
        $cruds = array_merge(
            ClassFinder::listed(self::core().'/Controller/Admin/Crud'),
            ClassFinder::listed($input->source.'/Controller/Admin/Crud'),
        );

        $maxLength = 0;
        if (!$input->crud) {
            foreach ($cruds as $crud) {
                $maxLength = max(\strlen($crud), $maxLength);
            }
        }

        [$locale, $availableLocales] = $this->languages($input->locale);

        if ($cruds) {
            $report->line('CRUD controller list: '.$input->crud);
        }
        foreach ($cruds as $crud) {
            if (!str_starts_with($crud, $input->crud)) {
                continue;
            }

            $trans = '';
            $space = '';
            foreach ($availableLocales as $currentLocale) {
                if (null !== $locale && $locale != $currentLocale) {
                    continue;
                }
                $prefix = null === $locale ? "\n\t - " : '';
                $space = null === $locale ? '' : str_repeat(' ', max($maxLength - \strlen($crud), 0));

                $path = camel2snake(str_strip(implode('.', tail(explode('\\', $crud), 4)), '', 'CrudController'));
                $trans .= $this->key(
                    '@'.Translator::DOMAIN_BACKEND.'.crud.'.$path.'.'.$input->action.'.'.$input->suffix,
                    $prefix.'@'.Translator::DOMAIN_BACKEND."[$currentLocale].<ln>crud.".$path.'.'.$input->action.'.'.$input->suffix.'</ln>',
                    $currentLocale,
                    ' (possible entity fallback)',
                );
            }

            $report->line(' * <info>'.trim($crud).'</info> '.$space.": $trans");
        }

        return $report;
    }
}
