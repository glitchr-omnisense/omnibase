<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Service\Model\ClassFinder;
use Omnibridge\Schema\CamelCaseNaming;

/**
 * translation:entities (plan "omnibase agnostic", step F5e, slice 3): the application's entities, then the core's -
 * their namespaces first, then their classes, the translations' tables (…Intl) left out - and, for each language,
 * the key of their noun, "@entities.<the path>.<suffix>", with its translation or "no translation found".
 */
final class TranslationEntities extends TranslationTask
{
    public function run(TranslationEntitiesInput $input, ?\Closure $sink = null): Report
    {
        $report = self::report($sink);
        $folders = [$input->source.'/Entity', self::core().'/Entity'];

        $entities = $namespaces = [];
        foreach ($folders as $folder) {
            array_push($entities, ...ClassFinder::namespaces($folder), ...ClassFinder::listed($folder));
            array_push($namespaces, ...ClassFinder::namespaces($folder));
        }

        [$locale, $availableLocales] = $this->languages($input->locale);

        if ($entities) {
            $report->line('Entity list: '.$input->entity);
        }
        foreach ($entities as $entity) {
            if (\in_array($entity, ['App\Entity', 'Base\Entity'], true) || str_ends_with($entity, CamelCaseNaming::TABLE_I18N_SUFFIX) || !str_starts_with($entity, $input->entity)) {
                continue;
            }

            $isNamespace = \in_array($entity, $namespaces);
            $entityStr = $entity.($isNamespace ? ' (namespace'.(class_exists($entity) ? ' <info>& class</info>' : '').')' : null);
            $color = $isNamespace ? 'magenta' : 'info';

            $trans = '';
            foreach ($availableLocales as $currentLocale) {
                if (null !== $locale && $locale != $currentLocale) {
                    continue;
                }

                $path = camel2snake(implode('.', tail(explode('\\', $entity), -2)), '.');
                $trans .= $this->key('@entities.'.$path.'.'.$input->suffix, "\n\t - @entities[$currentLocale].<ln>".$path.'.'.$input->suffix.'</ln>', $currentLocale);
            }

            $report->line(' * <'.$color.'>'.trim($entityStr).'</'.$color.'> '.": $trans");
        }

        return $report;
    }
}
