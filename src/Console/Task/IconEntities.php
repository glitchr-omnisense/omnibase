<?php

namespace Base\Console\Task;

use Base\Console\Report;
use Base\Service\Model\IconizeInterface;

/**
 * icon:entities (plan "omnibase agnostic", step F5e, slice 3): the entities that have icons (IconizeInterface) and
 * their class's icons, or an entity's rows and each one's icons.
 */
final class IconEntities extends EntitiesTask
{
    public function run(EntitiesInput $input, ?\Closure $sink = null): Report
    {
        $report = new Report($sink);
        $entityClass = $input->entity;

        if ($entityClass) {
            $entities = $this->rows($entityClass, IconizeInterface::class, $input->id);

            $maxLength = 0;
            foreach ($entities as $entity) {
                $maxLength = max(\strlen((string) $entity), $maxLength);
            }

            foreach ($entities as $entity) {
                $space = str_repeat(' ', max($maxLength - \strlen((string) $entity), 0));
                $icons = $entity->__iconize();
                $report->line('<info>'.$entityClass.' #'.$entity->getId()."</info>; <warning>$entity</warning> $space: [".implode(',', $icons ?? []).']');
            }

            return $report;
        }

        $entities = self::classes($input->source, IconizeInterface::class);
        if ($entities) {
            $report->line('Entity list: '.$entityClass);
        }
        foreach ($entities as $entity) {
            $icons = $entity::__iconizeStatic();
            $report->line(' * <info>'.$entity.'</info>: ['.implode(',', $icons ?? []).']');
        }

        return $report;
    }
}
