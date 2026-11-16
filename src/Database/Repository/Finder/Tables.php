<?php

namespace Base\Database\Repository\Finder;

use Base\Database\Mapping\EntityShapes;
use Omnibridge\Schema\Entity;
use Omnibridge\Schema\Inheritance;
use Omnibridge\Schema\Relation;

/**
 * The SQL of one entity class's rows, read from the neutral description (the shapes): its tables (a joined
 * hierarchy's from the root's down), the column of a field or of a path through any number of associations (each
 * association LEFT JOINed once), the quoting of the platform. What ServiceEntityRepository's common SQL is written
 * with - findValuesBy(), updateBy(), lockOneBy(), an order or a comparison of fields through associations - the same
 * on every persistence.
 *
 * @internal
 */
final class Tables
{
    /** @var list<string> the joins, in order */
    private array $joins = [];

    /** @var array<string, array{0: string, 1: string}> path => [alias prefix, class] */
    private array $joined = [];

    private int $count = 0;

    public function __construct(
        private readonly EntityShapes $shapes,
        private readonly string $class,
        private readonly string $platform,
    ) {
    }

    /** "FROM root t0 JOIN sub t1 ON ..." and every join a column asked for. */
    public function from(): string
    {
        return 'FROM '.$this->tablesOf($this->class, 't').($this->joins ? ' '.implode(' ', $this->joins) : '');
    }

    /** The identifier's column of the root table ("t0"."id"). */
    public function identifier(string $prefix = 't'): string
    {
        return $this->quote($prefix.'0').'.'.$this->quote($this->identifierColumn($this->class));
    }

    /** The root table, quoted. */
    public function rootTable(?string $class = null): string
    {
        return $this->quote($this->tables($class ?? $this->class)[0]);
    }

    /**
     * The rows of a class as one table, for SQL written by hand (ServiceEntityRepository::sql()'s `{Entity}`): its table;
     * for a class of a joined hierarchy, its tables joined from the root's down as one derived table holding every
     * column of the class once (the discriminator, the fields, the owning to-one associations' keys) - read only.
     */
    public function readable(string $class): string
    {
        $tables = $this->tables($class);
        if (\count($tables) < 2) {
            return $this->quote($tables[0]);
        }
        $entity = $this->entity($class);
        $columns = [];
        $root = $this->entity($entity->root ?? $entity->class);
        if (null !== ($discriminator = $root->inheritance?->column->name)) {
            $columns[$discriminator] = $this->quote('r0').'.'.$this->quote($discriminator);
        }
        foreach ($entity->fields as $field) {
            $columns[$field->column] ??= $this->quote($this->alias($class, 'r', $field->inheritedFrom)).'.'.$this->quote($field->column);
        }
        foreach ($entity->relations as $property => $relation) {
            if ($this->ownsColumn($class, $property)) {
                $columns[$relation->joinColumns[0]->name] ??= $this->quote($this->alias($class, 'r', $relation->inheritedFrom)).'.'.$this->quote($relation->joinColumns[0]->name);
            }
        }
        $select = [];
        foreach ($columns as $name => $column) {
            $select[] = $column.' AS '.$this->quote((string) $name);
        }

        return '(SELECT '.implode(', ', $select).' FROM '.$this->tablesOf($class, 'r').')';
    }

    /**
     * The column of a field, of an owning to-one association (its key), or of a path through associations ("author.email",
     * "lease.unit.organisation.name", an association at the end giving the related row's identifier); a key inside a JSON
     * field after "->" ("stats->score").
     */
    public function column(string $path): string
    {
        [$path, $json] = str_contains($path, '->') ? explode('->', $path, 2) : [$path, null];
        $segments = explode('.', $path);
        $class = $this->class;
        $prefix = 't';
        $walked = '';
        while (\count($segments) > 1 || (null !== $this->relation($class, $segments[0]) && !$this->ownsColumn($class, $segments[0]))) {
            $property = (string) array_shift($segments);
            $walked = '' === $walked ? $property : $walked.'.'.$property;
            [$prefix, $class] = $this->join($walked, $class, $prefix, $property);
            if ([] === $segments) {
                // An association at the end, kept by the other side or a join table: the related row's identifier.
                return $this->json($this->quote($prefix.'0').'.'.$this->quote($this->identifierColumn($class)), $json);
            }
        }

        return $this->json($this->own($class, $prefix, $segments[0]), $json);
    }

    public function quote(string $name): string
    {
        return 'mysql' === $this->platform ? '`'.str_replace('`', '``', $name).'`' : '"'.str_replace('"', '""', $name).'"';
    }

    /** The column of a field or of an owning to-one association, in the class's table that holds it. */
    public function own(string $class, string $prefix, string $property): string
    {
        $entity = $this->entity($class);
        if (isset($entity->fields[$property])) {
            $field = $entity->fields[$property];

            return $this->quote($this->alias($class, $prefix, $field->inheritedFrom)).'.'.$this->quote($field->column);
        }
        $relation = $entity->relations[$property] ?? throw new \Base\Database\Repository\UnsupportedQueryException(sprintf('%s has no field or association "%s".', $class, $property));
        if (!$this->ownsColumn($class, $property)) {
            throw new \Base\Database\Repository\UnsupportedQueryException(sprintf('%s::$%s keeps no column of its own.', $class, $property));
        }

        return $this->quote($this->alias($class, $prefix, $relation->inheritedFrom)).'.'.$this->quote($relation->joinColumns[0]->name);
    }

    /** The table a field or an owning to-one association of the class is written in (a joined hierarchy's own), quoted. */
    public function tableOf(string $property): string
    {
        $entity = $this->entity($this->class);
        $from = $entity->fields[$property]->inheritedFrom ?? $entity->relations[$property]->inheritedFrom ?? null;

        return $this->quote($this->tableFor($this->class, $from));
    }

    /** The bare column name of a field or an owning to-one association, quoted. */
    public function columnName(string $property): string
    {
        $entity = $this->entity($this->class);
        if (isset($entity->fields[$property])) {
            return $this->quote($entity->fields[$property]->column);
        }
        if ($this->ownsColumn($this->class, $property)) {
            return $this->quote($entity->relations[$property]->joinColumns[0]->name);
        }

        throw new \Base\Database\Repository\UnsupportedQueryException(sprintf('%s has no field or owning to-one association "%s" to write.', $this->class, $property));
    }

    /** The identifier's bare column name of a class, unquoted. */
    public function identifierColumn(string $class): string
    {
        $entity = $this->entity($class);
        $property = $entity->identifier[0] ?? throw new \LogicException(sprintf('%s has no identifier.', $class));

        return $entity->fields[$property]->column ?? throw new \LogicException(sprintf('%s is identified through an association: not read by the common SQL.', $class));
    }

    public function identifierProperty(string $class): string
    {
        return $this->entity($class)->identifier[0] ?? throw new \LogicException(sprintf('%s has no identifier.', $class));
    }

    /** The class's tables, the root's first (a joined hierarchy), or its one table. @return list<string> */
    public function tables(string $class): array
    {
        $entity = $this->entity($class);
        $root = $this->entity($entity->root ?? $entity->class);
        if (Inheritance::JOINED !== $root->inheritance?->type) {
            return [(string) ($root->table ?? $entity->table)];
        }
        $chain = [];
        for ($current = $entity; null !== $current; $current = null !== $current->parent && $this->shapes->has($current->parent) ? $this->entity($current->parent) : null) {
            if (Entity::ENTITY === $current->kind && null !== $current->table && !\in_array($current->table, $chain, true)) {
                array_unshift($chain, $current->table);
            }
            if ($current->class === $root->class) {
                break;
            }
        }

        return $chain;
    }

    private function json(string $column, ?string $key): string
    {
        if (null === $key) {
            return $column;
        }
        $path = '$.'.$key;

        return match ($this->platform) {
            'pgsql' => '('.$column."::jsonb #>> '{".str_replace('.', ',', $key)."}')",
            default => 'json_extract('.$column.", '".str_replace("'", "''", $path)."')",
        };
    }

    /** @return array{0: string, 1: class-string} the joined target's alias prefix and class */
    private function join(string $path, string $class, string $prefix, string $property): array
    {
        if (isset($this->joined[$path])) {
            return $this->joined[$path];
        }
        $relation = $this->relation($class, $property) ?? throw new \Base\Database\Repository\UnsupportedQueryException(sprintf('"%s": %s::$%s is no association.', $path, $class, $property));
        $target = $relation->target;
        $to = 'j'.(++$this->count).'_';
        $targetId = $this->quote($to.'0').'.'.$this->quote($this->identifierColumn($target));
        $ownerId = $this->quote($this->alias($class, $prefix, null, true)).'.'.$this->quote($this->identifierColumn($class));
        if ($relation->owning && [] !== $relation->joinColumns) {
            $on = $this->quote($to.'0').'.'.$this->quote($relation->joinColumns[0]->referencedColumn).' = '.$this->own($class, $prefix, $property);
            $this->joins[] = 'LEFT JOIN '.$this->tablesOf($target, $to, $on);
        } elseif ($relation->owning && null !== $relation->joinTable) {
            $link = $to.'jt';
            $this->joins[] = 'LEFT JOIN '.$this->quote($relation->joinTable->name).' '.$this->quote($link).' ON '.$this->quote($link).'.'.$this->quote($relation->joinTable->joinColumns[0]->name).' = '.$ownerId;
            $this->joins[] = 'LEFT JOIN '.$this->tablesOf($target, $to, $targetId.' = '.$this->quote($link).'.'.$this->quote($relation->joinTable->inverseJoinColumns[0]->name));
        } else {
            $inverse = $this->entity($target)->relations[$relation->mappedBy ?? ''] ?? throw new \Base\Database\Repository\UnsupportedQueryException(sprintf('"%s": %s::$%s has no owning side.', $path, $class, $property));
            if (null !== $inverse->joinTable) {
                $link = $to.'jt';
                $this->joins[] = 'LEFT JOIN '.$this->quote($inverse->joinTable->name).' '.$this->quote($link).' ON '.$this->quote($link).'.'.$this->quote($inverse->joinTable->inverseJoinColumns[0]->name).' = '.$ownerId;
                $this->joins[] = 'LEFT JOIN '.$this->tablesOf($target, $to, $targetId.' = '.$this->quote($link).'.'.$this->quote($inverse->joinTable->joinColumns[0]->name));
            } else {
                $column = $this->quote($this->alias($target, $to, $inverse->inheritedFrom)).'.'.$this->quote($inverse->joinColumns[0]->name);
                $this->joins[] = 'LEFT JOIN '.$this->tablesOf($target, $to, $column.' = '.$ownerId);
            }
        }

        return $this->joined[$path] = [$to, $target];
    }

    /**
     * The tables of a class aliased "<prefix>0", "<prefix>1"..., the first joined ON $on (a join), or the FROM; a single
     * table hierarchy's subclass restricted to its discriminator values in the ON.
     */
    private function tablesOf(string $class, string $prefix, ?string $on = null): string
    {
        $tables = $this->tables($class);
        $sql = $this->quote($tables[0]).' '.$this->quote($prefix.'0').(null === $on ? '' : ' ON '.$on);
        foreach (\array_slice($tables, 1, null, true) as $index => $table) {
            $sql .= ' '.(null === $on ? 'INNER' : 'LEFT').' JOIN '.$this->quote($table).' '.$this->quote($prefix.$index).' ON '.$this->quote($prefix.$index).'.'.$this->quote($this->identifierColumn($class)).' = '.$this->quote($prefix.'0').'.'.$this->quote($this->identifierColumn($class));
        }

        return $sql;
    }

    private function alias(string $class, string $prefix, ?string $inheritedFrom, bool $root = false): string
    {
        $tables = $this->tables($class);
        if ($root) {
            return $prefix.'0';
        }
        $index = array_search($this->tableFor($class, $inheritedFrom), $tables, true);

        return $prefix.(false === $index ? \count($tables) - 1 : $index);
    }

    private function tableFor(string $class, ?string $inheritedFrom): string
    {
        $tables = $this->tables($class);
        if (null === $inheritedFrom || \count($tables) < 2) {
            return $tables[\count($tables) - 1];
        }
        $table = $this->shapes->has($inheritedFrom) ? $this->entity($inheritedFrom)->table : null;

        return null !== $table && \in_array($table, $tables, true) ? $table : $tables[\count($tables) - 1];
    }

    private function ownsColumn(string $class, string $property): bool
    {
        $relation = $this->entity($class)->relations[$property] ?? null;

        return null !== $relation && $relation->isToOne() && $relation->owning && [] !== $relation->joinColumns;
    }

    private function relation(string $class, string $property): ?Relation
    {
        return $this->entity($class)->relations[$property] ?? null;
    }

    private function entity(string $class): Entity
    {
        return $this->shapes->shape($class)->entity;
    }
}
