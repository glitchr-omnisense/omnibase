<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Service\Model\AutocompleteInterface;

/**
 * autocomplete:entities (plan "omnibase agnostic", step F5e, slice 3): the entities a picker searches
 * (AutocompleteInterface), or an entity's rows and what each one answers a picker (its text and its data).
 */
final class AutocompleteEntities extends EntitiesTask
{
    public function run(EntitiesInput $input, ?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        $entityClass = $input->entity ?? '';

        if ($entityClass) {
            $entities = $this->rows($entityClass, AutocompleteInterface::class, $input->id);

            $maxLength = 0;
            foreach ($entities as $entity) {
                $maxLength = max(\strlen((string) $entity->__autocomplete()), $maxLength);
            }

            foreach ($entities as $entity) {
                $autocomplete = $entity->__autocomplete();
                $autocompleteData = $entity->__autocompleteData();
                if ($autocomplete) {
                    $autocompleteData = trim(str_replace(["\t", "\n"], ['', ' '], print_r($autocompleteData, true)));
                }

                $space = str_repeat(' ', max($maxLength - \strlen((string) $autocomplete), 0));
                $report->line('<info>'.$entityClass.' #'.$entity->getId().'</info>; <warning>Autocomplete = </warning>"'.$autocomplete.'"'.$space.'<warning> / Data = </warning>"'.(\is_array($autocompleteData) ? 'Array' : $autocompleteData).'"');
            }

            return $report;
        }

        $entities = self::classes($input->source, AutocompleteInterface::class);
        if ($entities) {
            $report->line('Entity candidate list: '.$entityClass);
        }
        foreach ($entities as $entity) {
            $report->line(' * <info>'.$entity.'</info>');
        }

        return $report;
    }
}
