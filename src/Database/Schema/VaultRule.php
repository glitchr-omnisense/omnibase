<?php

namespace Base\Database\Schema;

use Base\Database\Attribute\Vault;
use Base\Database\Entity\Extension\TranslationInterface;
use Base\Database\Walker\TranslatableWalker;
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Builder\Draft;
use Omnibridge\Schema\Builder\Rule;
use Omnibridge\Schema\Declaration\ClassDeclaration;

/**
 * #[Vault]'s constraints, as the attribute gives them to Doctrine: the unique
 * constraint <table>_unique over the columns it names, and on the root of a
 * translation hierarchy its vault column added to <table>_unique_translation
 * (before translatable_id and locale: TranslationRule comes after).
 */
final class VaultRule implements Rule
{
    public function before(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
    }

    public function declared(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
    }

    public function loaded(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        if ($class->isMappedSuperclass || null === ($vault = Attributes::ofClass($class->name, Vault::class))) {
            return;
        }

        $column = (string) ($vault['vault'] ?? 'vault');
        $unique = (array) ($vault['unique'] ?? []);
        if ($unique) {
            $name = $builder->naming()->classToTableName($class->name).'_unique';
            $class->table['uniqueConstraints'][$name]['columns'] = array_unique(array_merge($class->table['uniqueConstraints'][$name]['columns'] ?? [], $unique));
        }

        if (is_subclass_of($class->name, TranslationInterface::class) && $class->isRootEntity()) {
            $name = $builder->naming()->classToTableName($class->rootEntityName).'_'.TranslatableWalker::SALT;
            $class->table['uniqueConstraints'][$name] ??= [];
            $class->table['uniqueConstraints'][$name]['columns'] = array_unique(array_merge($class->table['uniqueConstraints'][$name]['columns'] ?? [], [$column]));
        }
    }
}
