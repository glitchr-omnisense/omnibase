<?php

namespace Base\Database\Repository;

use Base\Database\Attribute\Backend\Backends;
use Base\Database\Backend\BulkDeletes;
use Base\Database\Mapping\EntityShape;
use Base\Database\Repository\Finder\Aggregate;
use Base\Database\Repository\Finder\Change;
use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Field;
use Base\Database\Repository\Finder\Group;
use Base\Database\Repository\Finder\Parser;
use Base\Database\Repository\Finder\Tables;
use Omnibridge\Persistence\UnitOfWork;

/**
 * A repository of omnibase, whatever keeps the entities: the parent of Base\Repository\*, of a bundle's
 * repositories and of App\Repository\*. It is built from the persistence installed (Finders) and holds nothing of
 * an ORM: the finder language (findByStateAndLocale(), cacheOneBySlug(), countByDomain()...) is read by the core's
 * Finder\Parser and compiled by the persistence's engine, the writes go through its unit of work, and what the
 * language adds - findValuesBy(), updateBy(), deleteBy(), lockOneBy(), the relational criteria, Field::of(), sql() for
 * an order on an expression - is written once in common SQL around the engine's selection. Every query is written
 * ONCE: the same class runs on Doctrine and on Eloquent.
 *
 *     class ArticleRepository extends ServiceEntityRepository   // App\Repository\ArticleRepository: App\Entity\Article
 *     {
 *         public function published(int $limit): array
 *         {
 *             return $this->findByState('publish', [], ['publishedAt' => 'DESC'], $limit)->getResult();
 *         }
 *     }
 *
 * The entity's class is the repository's name read backwards (X\Repository\A\BRepository: X\Entity\A\B) unless it is
 * given. A repository with services of its own takes them beside the Finders and calls the parent's constructor.
 * An entity without a repository class gets this class itself (Repositories::of()).
 *
 * The finder's methods are known to PHPStan by Base\Tooling\PHPStan\FinderMethodsExtension (extension.neon).
 *
 * @template T of object
 *
 * @implements Entities<T>
 */
class ServiceEntityRepository implements Entities
{
    private readonly Finders $finders;

    /** @var class-string */
    private readonly string $entityName;

    private ?Finder $finder = null;

    /** The entity of the repository's name: App\Repository\Thread\TagRepository's is App\Entity\Thread\Tag. */
    public static function getFqcnEntityName(): string
    {
        return preg_replace(['/\\\\Repository\\\\/', '/Repository$/'], ['\\\\Entity\\\\', ''], static::class);
    }

    /** @param class-string|null $entityName the entity's class, read from the repository's name when not given */
    public function __construct(Finders $finders, ?string $entityName = null)
    {
        $this->finders = $finders;
        $this->entityName = $entityName ?? static::getFqcnEntityName();
    }

    /** The persistence the repository runs on. */
    protected function finders(): Finders
    {
        return $this->finders;
    }

    /** The engine of the repository's entity, asked at the first call: building a repository reads no mapping. */
    protected function finder(): Finder
    {
        return $this->finder ??= $this->finders->of($this->entityName);
    }

    protected function unitOfWork(): UnitOfWork
    {
        return $this->finders->unitOfWork();
    }

    /** The entity's shape: its fields, associations and table. */
    protected function shape(): EntityShape
    {
        return $this->finders->shapes()->shape($this->getClassName());
    }


    /** @return class-string the entity's class, as its mapping names it */
    public function getClassName(): string
    {
        return $this->finder()->className();
    }

    /**
     * The finder language.
     *
     * @param string      $method
     * @param list<mixed> $arguments
     */
    public function __call($method, $arguments): mixed
    {
        return $this->finder()->run((string) $method, $arguments);
    }

    public function find($id): ?object
    {
        return null === $id ? null : $this->finder()->find($id);
    }

    public function findAll(): array
    {
        return $this->finder()->run('findAll', [])->getResult();
    }

    /**
     * @param array<int|string, mixed> $criteria see Entities::findBy()
     *
     * @return list<T>
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
    {
        $criteria = $this->criteria($criteria);
        if (null === $criteria) {
            return [];
        }
        if ($this->ordersThroughAssociations($orderBy)) {
            return $this->orderedThroughAssociations($criteria, $orderBy ?? [], $limit, $offset);
        }

        return $this->finder()->run('findBy', [$criteria, $orderBy, $limit, $offset])->getResult();
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        $criteria = $this->criteria($criteria);
        if (null === $criteria) {
            return null;
        }
        if ($this->ordersThroughAssociations($orderBy)) {
            return $this->orderedThroughAssociations($criteria, $orderBy ?? [], 1, null)[0] ?? null;
        }

        return $this->finder()->run('findOneBy', [$criteria, $orderBy]);
    }

    /** The number of rows matching $criteria (all of them for []), counted by the database. */
    public function count(array $criteria = []): int
    {
        $criteria = $this->criteria($criteria);

        return null === $criteria ? 0 : (int) $this->finder()->run('count', [$criteria]);
    }

    /**
     * The rows matching $criteria deleted, their entities never read and no lifecycle event dispatched: a purge. The
     * whole criteria array (fields, lists, null, groups, operators, paths); plain `field => value` criteria in one
     * statement, any other first selected by the persistence's finder.
     *
     *     $requests->deleteBy([Group::of(Parser::SEPARATOR_AND, Criterion::of('createdAt', $before, Parser::OPTION_LOWER))]);
     *
     * @param array<int|string, mixed> $criteria
     *
     * @return int the rows deleted
     */
    public function deleteBy(array $criteria): int
    {
        $plain = true;
        foreach ($criteria as $field => $value) {
            if (\is_int($field) || str_contains($field, '.') || null === $value || [] === $value || $value instanceof Field || $value instanceof Finder\Key) {
                $plain = false;
                break;
            }
        }
        if ($plain) {
            return $this->evicted(Backends::of($this->unitOfWork(), BulkDeletes::class)->deleteWhere($this->getClassName(), $criteria));
        }
        $identifier = $this->tables()->identifierProperty($this->getClassName());
        $ids = array_column($this->findValuesBy([$identifier], $criteria, distinct: true), $identifier);
        $deleted = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $deleted += Backends::of($this->unitOfWork(), BulkDeletes::class)->deleteWhere($this->getClassName(), [$identifier => $chunk]);
        }

        return $this->evicted($deleted);
    }

    /**
     * Values of the rows rather than entities: fields and paths (an association gives its key), aggregates over the
     * rows grouped by the plain values listed (Finder\Aggregate: count, sum, avg, min, max, countDistinct; day and hour
     * buckets of a moment), the same criteria as findBy(), an order on the values' names or on fields, `$having`
     * criteria on the values' names (`name => value`, or a Group of Criterion::of(name, value, comparison)), distinct
     * rows. One row per result, by the values' names (a field's own name when listed without one).
     *
     *     $credits->findValuesBy(['user', 'balance' => Aggregate::sum('minutes')], having: [Group::of(Parser::SEPARATOR_AND, Criterion::of('balance', 60, Parser::OPTION_LOWER))]);
     *
     * @param array<int|string, string|Aggregate> $values
     * @param array<int|string, mixed>           $criteria
     * @param array<string, string>|null         $orderBy
     * @param array<int|string, mixed>           $having
     *
     * @return list<array<string, mixed>>
     */
    public function findValuesBy(array $values, array $criteria = [], ?array $orderBy = null, $limit = null, $offset = null, array $having = [], bool $distinct = false): array
    {
        $criteria = $this->criteria($criteria);
        if (null === $criteria || [] === $values) {
            return [];
        }
        $tables = $this->tables();
        $expressions = $select = $groups = $aggregates = [];
        foreach ($values as $name => $value) {
            $name = \is_int($name) ? (string) $value : $name;
            $expression = $value instanceof Aggregate ? $this->aggregate($tables, $value) : $tables->column((string) $value);
            if ($value instanceof Aggregate && $value->aggregates()) {
                $aggregates[$name] = true;
            } else {
                $groups[] = $expression;
            }
            $expressions[$name] = $expression;
            $select[] = $expression.' AS '.$tables->quote($name);
        }
        $parameters = [];
        $havingSql = $this->conditions($having, static fn (string $name): string => $expressions[$name] ?? throw new \InvalidArgumentException(sprintf('No value named "%s" to compare in having.', $name)), $parameters);
        $order = [];
        foreach ($orderBy ?? [] as $name => $direction) {
            $order[] = ($expressions[$name] ?? $tables->column((string) $name)).' '.self::direction($direction);
        }

        $sql = 'SELECT '.($distinct ? 'DISTINCT ' : '').implode(', ', $select).' '.$tables->from()
            .' WHERE '.$tables->identifier().' IN (SELECT * FROM ({selection:0}) '.$tables->quote('selection').')'
            .($aggregates && $groups ? ' GROUP BY '.implode(', ', $groups) : '')
            .('' !== $havingSql ? ' HAVING '.$havingSql : '')
            .($order ? ' ORDER BY '.implode(', ', $order) : '')
            .self::limit($limit, $offset);
        $rows = $this->finders->sql($sql, $parameters, [[$this->getClassName(), $criteria]]);
        \assert(\is_array($rows));

        return array_map(static function (array $row) use ($aggregates): array {
            foreach (array_keys($aggregates) as $name) {
                if (\is_string($row[$name] ?? null) && is_numeric($row[$name])) {
                    $row[$name] = str_contains($row[$name], '.') || str_contains(strtolower($row[$name]), 'e') ? (float) $row[$name] : (int) $row[$name];
                }
            }

            return $row;
        }, array_values($rows));
    }

    /**
     * The rows matching $criteria changed in the database, no entity read and no lifecycle event dispatched (an
     * entity already loaded keeps its values: refresh it): fields and owning to-one associations set to a value, or
     * computed from the one the row holds (Finder\Change::add(), Change::replacePrefix()). The criteria on the row's
     * own fields are checked again by the UPDATE itself, so a guarded change (a balance never below zero) holds under
     * concurrency. The number of rows changed.
     *
     *     $debited = 1 === $users->updateBy(['id' => $id, Group::of(Parser::SEPARATOR_AND, Criterion::of('pepettes', $amount, Parser::OPTION_GREATER_EQUAL))], ['pepettes' => Change::add(-$amount)]);
     *
     * @param array<int|string, mixed> $criteria
     * @param array<string, mixed>     $values
     */
    public function updateBy(array $criteria, array $values): int
    {
        $read = $this->criteria($criteria);
        if (null === $read || [] === $values) {
            return 0;
        }
        $tables = $this->tables();
        $byTable = [];
        foreach ($values as $property => $value) {
            $column = $tables->columnName($property);
            $table = $tables->tableOf($property);
            $byTable[$table] ??= ['set' => [], 'parameters' => []];
            if ($value instanceof Change) {
                $byTable[$table]['set'][] = match ($value->operation) {
                    Change::ADD => $column.' = '.$column.' + ?',
                    Change::REPLACE_PREFIX => $column.' = '.('mysql' === $this->finders->platform() ? 'CONCAT(?, SUBSTRING('.$column.', ?))' : '? || SUBSTR('.$column.', ?)'),
                };
                array_push($byTable[$table]['parameters'], ...(Change::ADD === $value->operation ? [$value->arguments[0]] : [$value->arguments[1], mb_strlen((string) $value->arguments[0]) + 1]));
            } else {
                $byTable[$table]['set'][] = $column.' = ?';
                $byTable[$table]['parameters'][] = $this->bind($value);
            }
        }
        $identifier = $tables->quote($tables->identifierColumn($this->getClassName()));
        $changed = 0;
        foreach ($byTable as $table => $update) {
            $parameters = $update['parameters'];
            $guard = $this->guard($criteria, $tables, $table, $parameters);
            $changed = max($changed, (int) $this->finders->sql('UPDATE '.$table.' SET '.implode(', ', $update['set'])
                .' WHERE '.$identifier.' IN (SELECT * FROM ({selection:0}) '.$tables->quote('selection').')'.('' !== $guard ? ' AND '.$guard : ''),
                $parameters, [[$this->getClassName(), $read]]));
        }

        return $this->evicted($changed);
    }

    /**
     * Rows changed or deleted behind the persistence's back: the class's entries of its second-level cache dropped (a
     * cached find() would answer the old values), where the persistence keeps such a cache. The number given back.
     */
    private function evicted(int $rows): int
    {
        if ($rows > 0) {
            try {
                $unitOfWork = $this->unitOfWork();
            } catch (\Throwable) {
                return $rows; // Finders without a unit of work: no cache of entities either.
            }
            if (Backends::has($unitOfWork, \Base\Database\Backend\EntityCache::class)) {
                Backends::of($unitOfWork, \Base\Database\Backend\EntityCache::class)->evictClass($this->getClassName());
            }
        }

        return $rows;
    }

    /**
     * The first entity matching $criteria in that order, its row locked until the running transaction ends (SELECT ...
     * FOR UPDATE; with $skipLocked, a row another transaction holds is passed over: the next free one). Call it inside
     * UnitOfWork::transactional(). Its values read again from the database. Null when no row is free.
     *
     *     $last = $runs->lockOneBy(['repository' => $repository], ['number' => 'DESC']);
     *
     * @param array<int|string, mixed>   $criteria
     * @param array<string, string>|null $orderBy
     *
     * @return T|null
     */
    public function lockOneBy(array $criteria, ?array $orderBy = null, bool $skipLocked = false): ?object
    {
        $tables = $this->tables();
        $identifier = $tables->identifierProperty($this->getClassName());
        $candidates = array_column($this->findValuesBy([$identifier], $criteria, $orderBy, $skipLocked ? 50 : 1), $identifier);
        $platform = $this->finders->platform();
        foreach ($candidates as $id) {
            if ('sqlite' !== $platform) {
                $locked = $this->finders->sql('SELECT '.$tables->quote($tables->identifierColumn($this->getClassName())).' FROM '.$tables->rootTable()
                    .' WHERE '.$tables->quote($tables->identifierColumn($this->getClassName())).' = ? FOR UPDATE'.($skipLocked ? ' SKIP LOCKED' : ''), [$id]);
                if ([] === $locked) {
                    continue;
                }
            }
            // Read again under the lock: the row may have changed between the selection and the lock.
            $entity = $this->find($id);
            if (null === $entity || 0 === $this->count([...$criteria, Group::of(Parser::SEPARATOR_AND, Criterion::of($identifier, $id))])) {
                continue;
            }
            try {
                $this->unitOfWork()->refresh($entity);
            } catch (\Throwable) {
                // A persistence without a unit of work here: the entity was just read.
            }

            return $entity;
        }

        return null;
    }

    /**
     * ONE SQL body run on the persistence's connection, for what the language does not say (an order on an
     * expression...): tables and columns written `{Video}` and `{Video.views}` (the entity's short name or its class,
     * then a field or an owning to-one association), resolved from the mapping - `{Video}` of a joined hierarchy is its
     * tables joined as one (read only: write with updateBy()); positional `?` parameters. The rows
     * of a SELECT, the number of rows changed otherwise. Write the SQL every supported database reads.
     *
     *     $this->sql('SELECT v.{Video.id} AS id FROM {Video} v ORDER BY v.{Video.views} + COALESCE(v.{Video.legacyViews}, 0) DESC LIMIT 10');
     *
     * @param list<mixed> $parameters
     *
     * @return list<array<string, mixed>>|int
     */
    protected function sql(string $sql, array $parameters = []): array|int
    {
        $sql = preg_replace_callback('/\{([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)(?:\.([A-Za-z_][A-Za-z0-9_]*))?\}/', function (array $match): string {
            $class = $this->entityNamed($match[1]);
            $tables = new Tables($this->finders->shapes(), $class, $this->finders->platform());

            return isset($match[2]) && '' !== $match[2]
                ? ($match[2] === $tables->identifierProperty($class) ? $tables->quote($tables->identifierColumn($class)) : (new Tables($this->finders->shapes(), $class, $this->finders->platform()))->columnName($match[2]))
                : $tables->readable($class);
        }, $sql) ?? $sql;

        return $this->finders->sql($sql, array_map($this->bind(...), array_values($parameters)));
    }

    /**
     * A criteria array as findBy(), findOneBy() and count() read it, on every persistence: `field => null` is
     * "IS NULL" (a field, a path, an association: no row is ever `= NULL`), and `field => []` matches nothing (a
     * list is "one of these": of none, no row) - null is returned then, and the caller answers without a query.
     * A Finder\Key, a Finder\Group and the other values are left as they are.
     *
     * @param array<int|string, mixed> $criteria
     *
     * @return array<int|string, mixed>|null
     */
    private function criteria(array $criteria): ?array
    {
        $read = [];
        $relational = [];
        foreach ($criteria as $field => $value) {
            if (\is_int($field) && $value instanceof Criterion && $value->isRelational()) {
                $relational[] = $value;
            } elseif (\is_int($field) && $value instanceof Group && $value->isRead()) {
                $read[] = $this->group($value);
            } elseif (!\is_int($field) && $value instanceof Field) {
                $read[] = Group::of(Parser::SEPARATOR_AND, $this->member(Criterion::of($field, $value)));
            } elseif (!\is_int($field) && null !== ($through = $this->throughAssociation($field, $value))) {
                // A path across two associations or more: the related records matching the rest of the path first.
                if ([] === $through[1]) {
                    return null;
                }
                $read[$through[0]] = $through[1];
            } elseif (\is_int($field) || str_starts_with($field, 'special:')) {
                $read[$field] = $value;
            } elseif (null === $value) {
                $read[] = Group::of(Parser::SEPARATOR_AND, Criterion::of($field, null, Parser::OPTION_NULL));
            } elseif ([] === $value) {
                return null;
            } else {
                $read[$field] = $value;
            }
        }
        foreach ($relational as $criterion) {
            $read = $this->relational($criterion, $read);
            if (null === $read) {
                return null;
            }
        }

        return $read;
    }

    /**
     * Criterion::exists(), notExists(), hasAll(), greatestPer(): the identifiers of the rows they select, read with the
     * language first, then `identifier IN (...)` (NOT IN for notExists()). Null when no row can match.
     *
     * @param array<int|string, mixed> $read the other criteria, already read
     *
     * @return array<int|string, mixed>|null
     */
    private function relational(Criterion $criterion, array $read): ?array
    {
        $identifier = $this->tables()->identifierProperty($this->getClassName());
        switch ($criterion->field) {
            case Criterion::EXISTS:
            case Criterion::NOT_EXISTS:
                [$class, $pointingTo, $criteria] = $criterion->value;
                $related = array_values(array_filter(array_column((new self($this->finders, $class))->findValuesBy([$pointingTo], [...$criteria, Group::of(Parser::SEPARATOR_AND, Criterion::of($pointingTo, null, Parser::OPTION_NOT_NULL))], distinct: true), $pointingTo), static fn (mixed $id): bool => null !== $id));
                if (Criterion::EXISTS === $criterion->field) {
                    if ([] === $related) {
                        return null;
                    }
                    $read[] = Group::of(Parser::SEPARATOR_AND, Criterion::of($identifier, $related));
                } elseif ([] !== $related) {
                    $read[] = Group::of(Parser::SEPARATOR_AND, Criterion::of($identifier, $related, Parser::OPTION_NOT_EQUAL));
                }

                return $read;
            case Criterion::HAS_ALL:
                [$path, $values] = $criterion->value;
                $ids = null;
                foreach ($values as $value) {
                    $matching = array_column($this->findValuesBy([$identifier], [$path => $value], distinct: true), $identifier);
                    $ids = null === $ids ? $matching : array_values(array_intersect($ids, $matching));
                    if ([] === $ids) {
                        return null;
                    }
                }
                if (null !== $ids) {
                    $read[] = Group::of(Parser::SEPARATOR_AND, Criterion::of($identifier, $ids));
                }

                return $read;
            case Criterion::GREATEST_PER:
                [$field, $per] = $criterion->value;
                $members = [];
                foreach ($this->findValuesBy([$per, 'greatest' => Aggregate::max($field)], $read) as $row) {
                    if (null === $row['greatest']) {
                        continue;
                    }
                    $members[] = Group::of(Parser::SEPARATOR_AND,
                        null === $row[$per] ? Criterion::of($per, null, Parser::OPTION_NULL) : Criterion::of($per, $row[$per]),
                        Criterion::of($field, $row['greatest']),
                    );
                }
                if ([] === $members) {
                    return null;
                }
                $read[] = Group::of(Parser::SEPARATOR_OR, ...$members);

                return $read;
        }

        throw new \LogicException(sprintf('No relational criterion "%s".', $criterion->field));
    }

    /** A group read again: its members through two associations or more, and those compared to another field, resolved. */
    private function group(Group $group): Group
    {
        $members = [];
        $changed = false;
        foreach ($group->criterions() as $criterion) {
            if ($criterion->value instanceof Group) {
                $members[] = $this->group($criterion->value);
                $changed = true;
                continue;
            }
            $member = $this->member($criterion);
            $changed = $changed || $member !== $criterion;
            $members[] = $member;
        }

        return $changed ? Group::of($group->separator(), ...$members) : $group;
    }

    /**
     * One member of a group: compared to another field (Finder\Field) - the identifiers of the rows where the two
     * compare, in SQL -, or through two associations or more - the related records matching the rest, by their own
     * repository -; else as it is.
     */
    private function member(Criterion $criterion): Criterion
    {
        $words = array_values($criterion->options);
        if ($criterion->value instanceof Field) {
            $tables = $this->tables();
            $operator = match (true) {
                \in_array(Parser::OPTION_NOT_EQUAL, $words, true) => '<>',
                \in_array(Parser::OPTION_GREATER, $words, true) => '>',
                \in_array(Parser::OPTION_GREATER_EQUAL, $words, true) => '>=',
                \in_array(Parser::OPTION_LOWER, $words, true) => '<',
                \in_array(Parser::OPTION_LOWER_EQUAL, $words, true) => '<=',
                default => '=',
            };
            $condition = $tables->column($criterion->field).' '.$operator.' '.$tables->column($criterion->value->path);
            $identifier = $tables->identifierColumn($this->getClassName());
            $rows = $this->finders->sql('SELECT DISTINCT '.$tables->identifier().' AS '.$tables->quote('id').' '.$tables->from().' WHERE '.$condition, []);
            \assert(\is_array($rows));
            $ids = array_column($rows, 'id');

            return [] === $ids ? $this->nothing() : Criterion::of($tables->identifierProperty($this->getClassName()), $ids);
        }
        $path = explode('.', $criterion->field);
        if (\count($path) >= 3 && $this->shape()->hasAssociation($path[0])) {
            $target = $this->shape()->targetClass($path[0]) ?? throw new UnsupportedQueryException(sprintf('"%s": %s::$%s names no target class.', $criterion->field, $this->getClassName(), $path[0]));
            $related = (new self($this->finders, $target))->findBy([Group::of(Parser::SEPARATOR_AND, Criterion::of(implode('.', \array_slice($path, 1)), $criterion->value, ...$words))]);

            return [] === $related ? $this->nothing() : Criterion::of($path[0], array_values($related));
        }

        return $criterion;
    }

    /**
     * A member no row matches (its identifier IS NULL): what an empty resolution stands for where it is - FALSE in an
     * And as in an Or, never "no condition".
     */
    private function nothing(): Criterion
    {
        return Criterion::of($this->tables()->identifierProperty($this->getClassName()), null, Parser::OPTION_NULL);
    }

    /** Whether an order goes through two associations or more (or into a JSON field): the common SQL orders it. */
    private function ordersThroughAssociations(?array $orderBy): bool
    {
        foreach (array_keys($orderBy ?? []) as $name) {
            $path = explode('.', (string) $name);
            if (str_contains((string) $name, '->') || (\count($path) >= 3 && $this->shape()->hasAssociation($path[0]))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int|string, mixed> $criteria already read
     * @param array<string, mixed>     $orderBy
     *
     * @return list<T>
     */
    private function orderedThroughAssociations(array $criteria, array $orderBy, $limit, $offset): array
    {
        $identifier = $this->tables()->identifierProperty($this->getClassName());
        $ids = array_column($this->findValuesBy([$identifier], $criteria, $orderBy, $limit, $offset), $identifier);
        if ([] === $ids) {
            return [];
        }
        $byId = [];
        foreach ($this->finder()->run('findBy', [[$identifier => $ids]])->getResult() as $entity) {
            $byId[(string) $this->identify($entity)] = $entity;
        }

        return array_values(array_filter(array_map(static fn (mixed $id): ?object => $byId[(string) $id] ?? null, $ids)));
    }

    private function tables(): Tables
    {
        return new Tables($this->finders->shapes(), $this->getClassName(), $this->finders->platform());
    }

    /** An aggregate or a bucket, in SQL. */
    private function aggregate(Tables $tables, Aggregate $aggregate): string
    {
        $column = null === $aggregate->field ? '*' : $tables->column($aggregate->field);

        return match ($aggregate->function) {
            Aggregate::COUNT => 'COUNT('.$column.')',
            Aggregate::COUNT_DISTINCT => 'COUNT(DISTINCT '.$column.')',
            Aggregate::SUM => 'SUM('.$column.')',
            Aggregate::AVG => 'AVG('.$column.')',
            Aggregate::MIN => 'MIN('.$column.')',
            Aggregate::MAX => 'MAX('.$column.')',
            Aggregate::DAY => match ($this->finders->platform()) {
                'pgsql' => 'CAST('.$column.' AS DATE)',
                default => 'DATE('.$column.')',
            },
            Aggregate::HOUR => match ($this->finders->platform()) {
                'mysql' => "DATE_FORMAT(".$column.", '%Y-%m-%d %H:00:00')",
                'pgsql' => "TO_CHAR(DATE_TRUNC('hour', ".$column."), 'YYYY-MM-DD HH24:00:00')",
                default => "STRFTIME('%Y-%m-%d %H:00:00', ".$column.')',
            },
        };
    }

    /**
     * Conditions on named expressions (`name => value`, lists as IN, null as IS NULL, groups of Criterion::of() with a
     * comparison), joined by And; their parameters appended.
     *
     * @param array<int|string, mixed> $criteria
     * @param \Closure(string): string $expression
     * @param list<mixed>              $parameters
     */
    private function conditions(array $criteria, \Closure $expression, array &$parameters, string $separator = Parser::SEPARATOR_AND): string
    {
        $sql = [];
        foreach ($criteria as $name => $value) {
            if ($value instanceof Group) {
                $members = [];
                foreach ($value->criterions() as $member) {
                    $members[] = $member->value instanceof Group ? [0 => $member->value] : [$member->field => [$member->options, $member->value]];
                }
                $inner = [];
                foreach ($members as $member) {
                    $key = array_key_first($member);
                    $inner[] = \is_int($key) ? '('.$this->conditions([$member[$key]], $expression, $parameters).')' : $this->comparison($expression((string) $key), $member[$key][0], $member[$key][1], $parameters);
                }
                $sql[] = '('.implode(Parser::SEPARATOR_OR === $value->separator() ? ' OR ' : ' AND ', $inner).')';
            } elseif ($value instanceof Criterion) {
                $sql[] = $this->comparison($expression($value->field), $value->options, $value->value, $parameters);
            } else {
                $sql[] = $this->comparison($expression((string) $name), [], $value, $parameters);
            }
        }

        return implode(Parser::SEPARATOR_OR === $separator ? ' OR ' : ' AND ', $sql);
    }

    /**
     * @param list<string> $words
     * @param list<mixed>  $parameters
     */
    private function comparison(string $expression, array $words, mixed $value, array &$parameters): string
    {
        if (\in_array(Parser::OPTION_NULL, $words, true) || (null === $value && !\in_array(Parser::OPTION_NOT_NULL, $words, true) && !\in_array(Parser::OPTION_NOT_EQUAL, $words, true))) {
            return $expression.' IS NULL';
        }
        if (\in_array(Parser::OPTION_NOT_NULL, $words, true) || (null === $value && \in_array(Parser::OPTION_NOT_EQUAL, $words, true))) {
            return $expression.' IS NOT NULL';
        }
        $operator = match (true) {
            \in_array(Parser::OPTION_NOT_EQUAL, $words, true) => '<>',
            \in_array(Parser::OPTION_GREATER, $words, true) => '>',
            \in_array(Parser::OPTION_GREATER_EQUAL, $words, true) => '>=',
            \in_array(Parser::OPTION_LOWER, $words, true) => '<',
            \in_array(Parser::OPTION_LOWER_EQUAL, $words, true) => '<=',
            [] === array_diff($words, [Parser::OPTION_EQUAL]) => '=',
            default => throw new UnsupportedQueryException(sprintf('Only comparisons are read here, not %s.', implode(', ', $words))),
        };
        if (\is_array($value)) {
            if ([] === $value) {
                return '<>' === $operator ? '1 = 1' : '1 = 0';
            }
            array_push($parameters, ...array_map($this->bind(...), array_values($value)));

            return $expression.('<>' === $operator ? ' NOT IN (' : ' IN (').implode(', ', array_fill(0, \count($value), '?')).')';
        }
        $parameters[] = $this->bind($value);

        return $expression.' '.$operator.' ?';
    }

    /**
     * The criteria on the row's own fields that an UPDATE of that table checks again itself (a guarded change):
     * `field => value` and the comparisons of an And group, on columns of $table.
     *
     * @param array<int|string, mixed> $criteria as given
     * @param list<mixed>              $parameters
     */
    private function guard(array $criteria, Tables $tables, string $table, array &$parameters): string
    {
        $own = [];
        $shape = $this->shape();
        $inTable = static function (string $field) use ($shape, $tables, $table): bool {
            return !str_contains($field, '.') && ($shape->hasField($field) || ($shape->hasAssociation($field) && $shape->isToOne($field) && $shape->isOwningSide($field))) && $tables->tableOf($field) === $table;
        };
        foreach ($criteria as $field => $value) {
            if (\is_string($field) && $inTable($field) && (\is_scalar($value) || $value instanceof \BackedEnum || $value instanceof \DateTimeInterface)) {
                $own[] = Criterion::of($field, $value);
            } elseif ($value instanceof Group && $value->isRead() && Parser::SEPARATOR_AND === $value->separator()) {
                foreach ($value->criterions() as $member) {
                    if (!$member->value instanceof Group && !$member->value instanceof Field && $inTable($member->field)
                        && [] === array_diff($member->options, [Parser::OPTION_EQUAL, Parser::OPTION_NOT_EQUAL, Parser::OPTION_GREATER, Parser::OPTION_GREATER_EQUAL, Parser::OPTION_LOWER, Parser::OPTION_LOWER_EQUAL])) {
                        $own[] = $member;
                    }
                }
            }
        }

        return $this->conditions($own, static fn (string $field): string => $tables->columnName($field), $parameters);
    }

    /** A value as the database holds it: a moment in UTC, an enumeration's value, an entity's identifier, a boolean as 1/0. */
    private function bind(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => \DateTimeImmutable::createFromInterface($value)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $value instanceof \BackedEnum => $value->value,
            \is_bool($value) => (int) $value,
            // An entity by its identifier, even one that prints as a string (__toString()), as the finders bind it.
            \is_object($value) && ($this->finders->shapes()->has($value) || !$value instanceof \Stringable) => $this->identify($value),
            $value instanceof \Stringable => (string) $value,
            default => $value,
        };
    }

    /** An entity's identifier, read through its mapping (any entity, a public $id or a getter). */
    private function identify(object $entity): mixed
    {
        try {
            return $this->unitOfWork()->identifier($entity);
        } catch (\Throwable) {
            $shapes = $this->finders->shapes();
            $class = $shapes->shape($entity)->className();
            $property = (new Tables($shapes, $class, 'sqlite'))->identifierProperty($class);
            for ($reflection = new \ReflectionClass($entity); false !== $reflection; $reflection = $reflection->getParentClass()) {
                if ($reflection->hasProperty($property)) {
                    return $reflection->getProperty($property)->getValue($entity);
                }
            }

            return null;
        }
    }

    /** The entity class `{Name}` names in sql(): this repository's, a class of that short name, or the class itself. */
    private function entityNamed(string $name): string
    {
        if (class_exists($name) && $this->finders->shapes()->has($name)) {
            return $name;
        }
        $own = $this->getClassName();
        if (substr($own, (int) strrpos('\\'.$own, '\\')) === $name) {
            return $own;
        }
        $found = array_values(array_filter($this->finders->shapes()->classes(), static fn (string $class): bool => substr($class, (int) strrpos('\\'.$class, '\\')) === $name));

        return 1 === \count($found) ? $found[0] : throw new \InvalidArgumentException(sprintf('"{%s}" names %s entity: write its class.', $name, [] === $found ? 'no' : 'more than one'));
    }

    private static function direction(mixed $direction): string
    {
        $direction = strtoupper((string) $direction);

        return \in_array($direction, ['ASC', 'DESC'], true) ? $direction : throw new \InvalidArgumentException(sprintf('An order is ASC or DESC, not "%s".', $direction));
    }

    private static function limit(mixed $limit, mixed $offset): string
    {
        $limit = null === $limit ? null : (int) $limit;
        $offset = null === $offset ? 0 : (int) $offset;
        if (null === $limit) {
            return $offset > 0 ? ' LIMIT '.\PHP_INT_MAX.' OFFSET '.$offset : '';
        }

        return ' LIMIT '.$limit.($offset > 0 ? ' OFFSET '.$offset : '');
    }

    /**
     * `lease.unit.organisation => $value` (two associations deep or more): the leases whose `unit.organisation` matches,
     * read by the leases' own repository (the same language, recursively), then `lease => those leases`. The same on
     * every persistence: each compiler only joins one association. Null when the field is no such path.
     *
     * @return array{0: string, 1: list<object>}|null
     */
    private function throughAssociation(string $field, mixed $value): ?array
    {
        $path = explode('.', $field);
        if (\count($path) < 3 || str_starts_with($field, 'special:') || !$this->shape()->hasAssociation($path[0])) {
            return null;
        }
        $target = $this->shape()->targetClass($path[0]) ?? throw new UnsupportedQueryException(sprintf('"%s": %s::$%s names no target class.', $field, $this->getClassName(), $path[0]));
        $related = (new self($this->finders, $target))->findBy([implode('.', \array_slice($path, 1)) => $value]);

        return [$path[0], array_values($related)];
    }

    /**
     * Query-by-Example: the criteria are the set fields of a (usually not persisted) select entity.
     *
     *     $select = (new Article())->setState('published');
     *     $repository->findByExample($select);   // == findBy(['state' => 'published'])
     *
     * Only mapped fields and to-one associations take part; uninitialized and null properties are skipped (a select
     * cannot say "IS NULL": findByStateNull() does). A select that sets nothing is refused, rather than the whole
     * table read.
     *
     * @return list<object>
     */
    public function findByExample(object $select, ?array $orderBy = null, $limit = null, $offset = null): array
    {
        $criteria = $this->criteriaFromSelect($select);
        if ([] === $criteria) {
            throw new \Exception(sprintf('findByExample() received a select (%s) with no queryable set fields — refusing to match the whole "%s" table. Use findAll() instead.', $select::class, $this->getClassName()));
        }

        return $this->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * The set, mapped fields of a select entity as findBy()'s criteria: what findByExample() and the finder
     * language's Model clause (findByModelAndIdGreaterThan($select, 0)) read.
     *
     * @return array<string, mixed>
     */
    public function criteriaFromSelect(object $select): array
    {
        $shape = $this->shape();
        $criteria = [];
        foreach ((new \ReflectionClass($select))->getProperties() as $property) {
            $name = $property->getName();
            if (!$shape->hasField($name) && !($shape->hasAssociation($name) && $shape->isToOne($name))) {
                continue;
            }
            if (!$property->isInitialized($select)) {
                continue;
            }
            $value = $property->getValue($select);
            if (null !== $value) {
                $criteria[$name] = $value;
            }
        }

        return $criteria;
    }

    /** @param mixed $entity checked here: anything else than an entity of the class is refused */
    public function persist($entity, bool $flush = false): void
    {
        $this->unitOfWork()->persist($this->ofClass($entity));
        if ($flush) {
            $this->unitOfWork()->flush();
        }
    }

    /** @param mixed $entity checked as persist() checks it */
    public function remove($entity, bool $flush = false): void
    {
        $this->unitOfWork()->remove($this->ofClass($entity));
        if ($flush) {
            $this->unitOfWork()->flush();
        }
    }

    /**
     * Writes what was persisted, changed or removed - and forgets the loaded records only when asked: a request's
     * signed-in user and every entity it held would be detached for the rest of it. A batch that wants its memory
     * back passes true.
     */
    public function flush(bool $autoclear = false)
    {
        $this->unitOfWork()->flush();
        if ($autoclear) {
            $this->clear();
        }
    }

    public function clear(): void
    {
        $this->finder()->clear();
    }

    private function ofClass(mixed $entity): object
    {
        $class = $this->getClassName();
        if (!\is_object($entity) || !$entity instanceof $class) {
            throw new \InvalidArgumentException(sprintf('Repository "%s" is expected "%s" entity, you passed "%s"', static::class, $class, \is_object($entity) ? $entity::class : 'null'));
        }

        return $entity;
    }
}
