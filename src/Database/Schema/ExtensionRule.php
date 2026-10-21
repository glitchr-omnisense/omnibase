<?php

namespace Base\Database\Schema;

use Base\Entity\Extension\Abstract\AbstractExtension;
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Builder\Draft;
use Omnibridge\Schema\Builder\Rule;
use Omnibridge\Schema\Declaration\ClassDeclaration;

/**
 * The extensions' index, as Base\EntitySubscriber\ExtensionSubscriber gives
 * it to Doctrine: on AbstractExtension's table, <table>_entity over
 * entityClass and entityId (an index, not a unique constraint: a Revision is
 * one row per flush).
 */
final class ExtensionRule implements Rule
{
    public function before(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
    }

    public function declared(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
    }

    public function loaded(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        if (AbstractExtension::class !== $class->name) {
            return;
        }

        $name = $builder->naming()->classToTableName(AbstractExtension::class).'_entity';
        $class->table['indexes'][$name]['columns'] = array_unique(array_merge($class->table['indexes'][$name]['columns'] ?? [], ['entityClass', 'entityId']));
    }
}
