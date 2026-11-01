<?php

namespace Base\Database\Repository;

use Base\Database\Entity\EntityHydrator;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Event\DoctrineQueryEventArgs;
use Base\Database\Events;
use Base\Database\Repository\Finder\Parser;
use Base\Database\Repository\Finder\Query as FinderQuery;
use Base\Database\Repository\Finder\Subject;
use Base\Service\Model\IntlDateTime;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Expr\Func;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\Persistence\Proxy;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Exception;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;

/**
 * The finder language on Doctrine: Finder\Parser reads a finder's name and
 * arguments (read()), this class compiles the Query into a QueryBuilder and runs
 * it (execute()) - the DQL, the joins, the date bounds, the DQL functions, the
 * second-level and query caches, the eager loading. The parse is the core's, free
 * of Doctrine (plan "omnibase agnostic", 2026-11-01, step F1); this compiler moves
 * to omnibridge/doctrine with the rest of Doctrine at step F3.
 */
class ServiceEntityParser implements Subject
{
    protected $method;
    protected $args;

    public const ALIAS_ENTITY = 'e';

    // The words of the finder language, Finder\Parser's (the names kept for the code that reads them here).
    public const REQUEST_FIND = Parser::REQUEST_FIND;
    public const REQUEST_CACHE = Parser::REQUEST_CACHE;
    public const REQUEST_COUNT = Parser::REQUEST_COUNT;
    public const REQUEST_DISTINCT = Parser::REQUEST_DISTINCT;
    public const REQUEST_LENGTH = Parser::REQUEST_LENGTH;
    public const LOAD_EAGERLY = Parser::LOAD_EAGERLY;
    public const SPECIAL_ALL = Parser::SPECIAL_ALL;
    public const SPECIAL_ONE = Parser::SPECIAL_ONE;
    public const SPECIAL_RAND = Parser::SPECIAL_RAND;
    public const SPECIAL_ATMOST = Parser::SPECIAL_ATMOST;
    public const SPECIAL_LASTONE = Parser::SPECIAL_LASTONE;
    public const SPECIAL_LAST = Parser::SPECIAL_LAST;
    public const SPECIAL_PREVONE = Parser::SPECIAL_PREVONE;
    public const SPECIAL_PREV = Parser::SPECIAL_PREV;
    public const SPECIAL_NEXTONE = Parser::SPECIAL_NEXTONE;
    public const SPECIAL_NEXT = Parser::SPECIAL_NEXT;
    public const OPTION_EMPTY = Parser::OPTION_EMPTY;
    public const OPTION_NOT_EMPTY = Parser::OPTION_NOT_EMPTY;
    public const OPTION_TRUE = Parser::OPTION_TRUE;
    public const OPTION_NOT_TRUE = Parser::OPTION_NOT_TRUE;
    public const OPTION_FALSE = Parser::OPTION_FALSE;
    public const OPTION_NOT_FALSE = Parser::OPTION_NOT_FALSE;
    public const OPTION_NULL = Parser::OPTION_NULL;
    public const OPTION_NOT_NULL = Parser::OPTION_NOT_NULL;
    public const OPTION_EQUAL = Parser::OPTION_EQUAL;
    public const OPTION_NOT_EQUAL = Parser::OPTION_NOT_EQUAL;
    public const OPTION_GREATER = Parser::OPTION_GREATER;
    public const OPTION_GREATER_EQUAL = Parser::OPTION_GREATER_EQUAL;
    public const OPTION_LOWER = Parser::OPTION_LOWER;
    public const OPTION_LOWER_EQUAL = Parser::OPTION_LOWER_EQUAL;
    public const OPTION_WITHIN = Parser::OPTION_WITHIN;
    public const OPTION_OLDER = Parser::OPTION_OLDER;
    public const OPTION_OLDER_EQUAL = Parser::OPTION_OLDER_EQUAL;
    public const OPTION_YOUNGER = Parser::OPTION_YOUNGER;
    public const OPTION_YOUNGER_EQUAL = Parser::OPTION_YOUNGER_EQUAL;
    public const OPTION_OVER = Parser::OPTION_OVER;
    public const OPTION_NOT_OVER = Parser::OPTION_NOT_OVER;
    public const OPTION_STARTING_WITH = Parser::OPTION_STARTING_WITH;
    public const OPTION_ENDING_WITH = Parser::OPTION_ENDING_WITH;
    public const OPTION_NOT_STARTING_WITH = Parser::OPTION_NOT_STARTING_WITH;
    public const OPTION_NOT_ENDING_WITH = Parser::OPTION_NOT_ENDING_WITH;
    public const OPTION_INSENSITIVE = Parser::OPTION_INSENSITIVE;
    public const OPTION_WITH_ROUTE = Parser::OPTION_WITH_ROUTE;
    public const OPTION_PARTIAL = Parser::OPTION_PARTIAL;
    public const OPTION_MODEL = Parser::OPTION_MODEL;
    public const OPTION_CLASSOF = Parser::OPTION_CLASSOF;
    public const OPTION_NOT_CLASSOF = Parser::OPTION_NOT_CLASSOF;
    public const OPTION_INSTANCEOF = Parser::OPTION_INSTANCEOF;
    public const OPTION_NOT_INSTANCEOF = Parser::OPTION_NOT_INSTANCEOF;
    public const OPTION_MEMBEROF = Parser::OPTION_MEMBEROF;
    public const OPTION_NOT_MEMBEROF = Parser::OPTION_NOT_MEMBEROF;
    public const OPTION_BUT = Parser::OPTION_BUT;
    public const OPTION_CLOSESTTO = Parser::OPTION_CLOSESTTO;
    public const OPTION_FARESTTO = Parser::OPTION_FARESTTO;
    public const SEPARATOR = Parser::SEPARATOR;
    public const SEPARATOR_OR = Parser::SEPARATOR_OR;
    public const SEPARATOR_AND = Parser::SEPARATOR_AND;
    public const SEPARATOR_FOR = Parser::SEPARATOR_FOR;
    public const SEPARATOR_BY = Parser::SEPARATOR_BY;
    public const COUNT_ALL = Parser::COUNT_ALL;
    public const COUNT_DISTINCT = Parser::COUNT_DISTINCT;

    protected static function getSpecial(string $special): string
    {
        return Parser::getSpecial($special);
    }

    protected static function getSpecials(): array
    {
        return Parser::getSpecials();
    }

    protected $operator = null;
    protected mixed $column = null;
    protected bool $cacheable = false;
    protected array|bool $eagerly = false;
    protected array $criteria = [];
    protected array $options = [];

    protected ?Parser $finder = null;

    protected mixed $classMetadata = null;

    /**
     * @var ClassMetadataManipulator|null
     */
    protected ?ClassMetadataManipulator $classMetadataManipulator = null;

    /**
     * @var ServiceEntityRepository
     */
    protected ServiceEntityRepository $serviceEntity;

    /**
     * @var EntityHydrator
     */
    protected EntityHydrator $entityHydrator;

    /**
     * @var EntityManager
     */
    protected EntityManager $entityManager;

    public function __construct(ServiceEntityRepository $serviceEntity, EntityManager $entityManager, ClassMetadataManipulator $classMetadataManipulator, EntityHydrator $entityHydrator)
    {
        $this->serviceEntity = $serviceEntity;
        $this->entityHydrator = $entityHydrator;
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;

        // The class the repository was built for (getClassName()): its name read from the repository's own
        // (getFqcnEntityName()) only when none was given - one built for an entity whose repository is a plain Doctrine one.
        $this->classMetadata = $entityManager->getClassMetadata($serviceEntity->getClassName());

        $this->criteria = [];
        $this->options = [];
        $this->operator = null;
        $this->eagerly = false;

        foreach (self::getSpecials() as $special) {
            $special = self::getSpecial($special);
            if ($special == self::SPECIAL_ALL) {
                continue;
            }

            $specialMethod = "__" . self::REQUEST_FIND . $special . self::SEPARATOR_BY;
            if (!method_exists($this, $specialMethod)) {
                throw new Exception("Special \"$special\" option is missing its find method : " . $specialMethod);
            }
        }
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param $limit
     * @param $offset
     * @param array|null $groupBy
     * @param array|null $selectAs
     * @return Query|null
     * @throws Exception
     */
    protected function __findBy(array $criteria = [], ?array $orderBy = null, $limit = null, $offset = null, ?array $groupBy = null, ?array $selectAs = null): ?Query
    {
        return $this->getQuery($criteria, $orderBy, $limit, $offset, $groupBy, $selectAs);
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param $limit
     * @param $offset
     * @param array|null $groupBy
     * @param array|null $selectAs
     * @return Query|null
     */
    protected function __findRandomlyBy(array $criteria = [], ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?array $groupBy = null, ?array $selectAs = null): ?Query
    {
        return $this->__findBy($criteria, array_merge(["id" => "rand"], $orderBy ?? []), $limit, $offset, $groupBy, $selectAs);
    }

    protected function __findAll(?array $orderBy = null, ?array $groupBy = null, ?array $selectAs = null): ?Query
    {
        return $this->__findBy([], $orderBy, null, null, $groupBy, $selectAs);
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param array|null $groupBy
     * @param array|null $selectAs
     * @return float|int|mixed|string|null
     * @throws NonUniqueResultException
     */
    protected function __findOneBy(array $criteria = [], ?array $orderBy = null, ?int $offset = null, ?array $groupBy = null, ?array $selectAs = null)
    {
        $results = $this->__findBy($criteria, $orderBy, 1, $offset, $groupBy, $selectAs);
        return $results->getOneOrNullResult();
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param array|null $groupBy
     * @param array|null $selectAs
     * @return float|int|mixed|string|null
     */
    protected function __findLastOneBy(array $criteria = [], ?array $orderBy = null, ?array $offset = null, ?array $groupBy = null, ?array $selectAs = null)
    {
        foreach($orderBy ?? [] as $field => $order)
            $orderBy[$field] = ($order == "DESC") ? "ASC" : "DESC";

        return $this->__findOneBy($criteria, array_merge($orderBy ?? [], ['id' => 'DESC']), $offset, $groupBy, $selectAs) ?? null;
    }

    protected function __findLastBy(array $criteria = [], ?array $orderBy = null, ?array $offset = null, ?array $groupBy = null, ?array $selectAs = null): ?Query
    {
        foreach($orderBy ?? [] as $field => $order)
            $orderBy[$field] = ($order == "DESC") ? "ASC" : "DESC";

        $limit = array_pop_key("special:last", $criteria) ?? 1;
        return $this->__findBy($criteria, array_merge($orderBy ?? [], ['id' => 'DESC']), $limit, $offset, $groupBy, $selectAs) ?? null;
    }

    protected function __findAtMostBy(array $criteria = [], ?array $orderBy = null, ?int $offset = null, ?array $groupBy = null, ?array $selectAs = null): ?Query
    {
        $limit = array_pop_key("special:atMost", $criteria);
        return $this->__findBy($criteria, $orderBy, $limit, $offset, $groupBy, $selectAs);
    }

    protected function __findPreviousBy(array $criteria = [], ?array $orderBy = null, ?array $offset = null, ?array $groupBy = null, ?array $selectAs = null): ?Query
    {
        foreach($orderBy ?? [] as $field => $order) // Flipping previous order provided..
            $orderBy[$field] = ($order == "DESC") ? "ASC" : "DESC";

        $limit = array_pop_key("special:prev", $criteria) ?? 1;
        $specialKeys = array_pop_key("special:prevKeys", $criteria) ?? [];
        foreach($specialKeys ?? [] as $field)
            $orderBy[$field] = "DESC";
        
        return $this->__findBy($criteria, array_merge($orderBy ?? [], ['id' => 'DESC']), $limit, $offset, $groupBy, $selectAs);
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param array|null $groupBy
     * @param array|null $selectAs
     * @return float|int|mixed|string|null
     */
     protected function __findPreviousOneBy(array $criteria = [], ?array $orderBy = null, ?array $offset = null, ?array $groupBy = null, ?array $selectAs = null)
     {
        return $this->__findPreviousBy($criteria, $orderBy, $offset, $groupBy, $selectAs)->GetOneOrNullResult();
     }
 
    protected function __findNextBy(array $criteria = [], ?array $orderBy = null, ?array $offset = null, ?array $groupBy = null, ?array $selectAs = null): ?Query
    {
        $limit = array_pop_key("special:next", $criteria) ?? 1;
        $specialKeys = array_pop_key("special:nextKeys", $criteria) ?? [];
        foreach($specialKeys ?? [] as $field)
            $orderBy[$field] = "ASC";

        return $this->__findBy($criteria, array_merge($orderBy ?? [], ['id' => 'ASC']), $limit, $offset, $groupBy, $selectAs);
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param array|null $groupBy
     * @param array|null $selectAs
     * @return float|int|mixed|string|null
     */
    protected function __findNextOneBy(array $criteria = [], ?array $orderBy = null, ?array $offset = null, ?array $groupBy = null, ?array $selectAs = null)
    {
        return $this->__findNextBy($criteria, $orderBy, $offset, $groupBy, $selectAs)->GetOneOrNullResult();
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param array|null $groupBy
     * @param $limit
     * @param $offset
     * @param array|null $selectAs
     * @return float|int|mixed|string
     * @throws Exception
     */
    protected function __lengthOfBy(array $criteria = [], ?array $orderBy = null, ?array $groupBy = null, $limit = null, $offset = null, ?array $selectAs = null)
    {
        return $this->getQueryWithLength($criteria, $orderBy, $limit, $offset, $groupBy, $selectAs)->getResult();
    }

    /**
     * @return int|array an integer; rows of counts when grouped
     */
    protected function __distinctCountBy(array $criteria = [], ?array $groupBy = null, ?array $selectAs = null): int|array
    {
        return $this->__countBy($criteria, self::COUNT_DISTINCT, null, $groupBy, $selectAs);
    }

    /**
     * Counts the rows matching $criteria: an integer. Grouped (a $groupBy or a
     * $selectAs), it gives the rows of the grouped query instead, each with
     * its "count" (ThreadRepository::countForChildrenIn reads them so).
     *
     * Ungrouped, the query used to select the entity next to COUNT() and group
     * by its id, so count([]) came back as one row per entity - an array where
     * ServiceEntityRepository::count() promises an int.
     *
     * @param array $criteria
     * @param string|null $mode
     * @param array|null $orderBy
     * @param array|null $groupBy
     * @param array|null $selectAs
     * @return int|array
     * @throws Exception
     */
    protected function __countBy(array $criteria = [], ?string $mode = null, ?array $orderBy = null, ?array $groupBy = null, ?array $selectAs = null)
    {
        $mode = $mode ?: self::COUNT_ALL;
        $query = $this->getQueryWithCount($criteria, $mode, $orderBy, $groupBy, $selectAs);
        if ($query === null) {
            return 0;
        }

        if (empty($groupBy) && empty($selectAs)) {
            return (int) $query->getSingleScalarResult();
        }

        return $query->getResult();
    }


    /**
     * Reads a finder's name and arguments into the finder language's neutral form
     * (Finder\Parser), nothing run.
     *
     * @param list<mixed> $arguments
     */
    public function read(string $method, array $arguments): FinderQuery
    {
        return $this->finder()->parse($method, $arguments);
    }

    /**
     * Reads a finder's name and arguments, then compiles and runs it on Doctrine.
     *
     * @param $method
     * @param $arguments
     * @return mixed
     * @throws MappingException
     */
    public function parse($method, $arguments): mixed
    {
        return $this->execute($this->read((string) $method, $arguments));
    }

    /**
     * Compiles a finder call read by Finder\Parser into Doctrine's query and runs
     * it: the internal request it names (__findBy, __findOneBy, __countBy...) with
     * its arguments, the criteria's options, the separator, the column, cache and
     * Eagerly set for the time of the call.
     */
    public function execute(FinderQuery $query): mixed
    {
        $this->criteria = $query->criteria;
        $this->options = $query->options;
        $this->operator = $query->separator;
        if (null !== $query->column) {
            $this->column = $query->column;
        }
        $this->cacheable = $query->cacheable;
        $this->eagerly = $query->eagerly;

        try {
            return $this->{$query->method}(...$query->arguments);
        } finally { // Reset internal variables, even if exception happens.
            // (e.g. wrong Query in Controller, but additional queries in Subscriber)

            $this->criteria = [];
            $this->options = [];
            $this->operator = null;
            $this->cacheable = false;
            $this->eagerly = false;
        }
    }

    /** The finder language's parser, on this entity (Subject) and Symfony's requests for WithRoute. */
    protected function finder(): Parser
    {
        return $this->finder ??= new Parser($this, static function (mixed $argument): array|null|false {
            $request = $argument instanceof Request ? $argument : ($argument instanceof KernelEvent ? $argument->getRequest() : null);

            return null === $request ? false : $request->attributes->get('_route_params');
        });
    }

    public function getClassName(): string
    {
        return $this->classMetadata->getName();
    }

    public function getFieldNames(): array
    {
        return array_values($this->classMetadataManipulator->getFieldNames($this->classMetadata->name));
    }

    public function hasField(string $name): bool
    {
        return $this->classMetadata->hasField($name) || $this->classMetadata->hasAssociation($name);
    }

    public function criteriaFromSelect(object $select): array
    {
        return $this->serviceEntity->criteriaFromSelect($select);
    }

    /**
     * @param $alias
     * @return string
     */
    protected function getAlias($alias)
    {
        return $this->classMetadataManipulator->getFieldName($this->classMetadata->name, $alias) ?? $alias;
    }


    /**
     * @return mixed|null
     */
    protected function getColumn()
    {
        return $this->column;
    }

    /**
     * @param string $column
     * @return $this
     */
    protected function setColumn(string $column)
    {
        $this->column = $column;
        return $this;
    }

    /**
     * @return mixed|null
     */
    protected function getSeparator()
    {
        return $this->operator;
    }

    /**
     * @param $operator
     * @return $this
     */
    protected function setSeparator($operator)
    {
        $this->operator = $operator;
        return $this;
    }


    /**
     * @param $date
     * @param $format
     * @return bool
     * @throws Exception
     */
    protected function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && IntlDateTime::createFromDateTime($d)->format($format) == $date;
    }

    /**
     * @param string $id
     * @return array|mixed
     */
    public function getCustomOption(string $id)
    {
        return $this->options[$id] ?? [];
    }

    /**
     * @param string $id
     * @param string $option
     * @return bool
     */
    public function findCustomOption(string $id, string $option)
    {
        return in_array($option, $this->getCustomOption($id));
    }




    /**
     * @param $className
     * @return string|null
     * @throws Exception
     */
    protected function getRealClassName($className): ?string
    {
        if (!class_exists($className)) {
            return null;
        }

        if (!is_instanceof($className, Proxy::class)) {
            return $className;
        }

        return get_parent_class($className);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param $field
     * @param $fieldValue
     * @return Andx|Comparison|Func|Orx|string
     * @throws EntityNotFoundException
     */
    /**
     * The mapped type to bind a date or time criterion with. Untyped, Doctrine
     * guesses "datetime" for any DateTime and sends the moment (in UTC), which
     * a DATE or TIME column never equals: findOneBy(['day' => $today]) found
     * nothing. Other values keep Doctrine's own inference.
     */
    protected function dateTypeOf(string $fieldHead, mixed $fieldValue): ?string
    {
        if (!$fieldValue instanceof \DateTimeInterface || !$this->classMetadata->hasField($fieldHead)) {
            return null;
        }

        $type = $this->classMetadata->getTypeOfField($fieldHead);

        // A bound the finder computes (YoungerThan, IsOver...) is a DateTime: on a field of an
        // immutable type it is bound with the mutable type of the same name, the same column format.
        if ($fieldValue instanceof DateTime && \is_string($type) && str_ends_with($type, '_immutable')) {
            return substr($type, 0, -\strlen('_immutable'));
        }

        return $type;
    }

    /**
     * The value a date or time criterion is bound with: a DateTimeImmutable on a field of a mutable
     * type (datetime, date, time) as the DateTime of the same moment, which the mutable type converts
     * (it refused the immutable one: findNextOneBy(['publishedAt' => $immutable]) threw).
     */
    protected function boundValueOf(string $fieldHead, mixed $fieldValue): mixed
    {
        if (!$fieldValue instanceof \DateTimeImmutable || !$this->classMetadata->hasField($fieldHead)) {
            return $fieldValue;
        }

        $type = $this->classMetadata->getTypeOfField($fieldHead);

        return \is_string($type) && str_ends_with($type, '_immutable') ? $fieldValue : DateTime::createFromImmutable($fieldValue);
    }

    protected function buildQueryExpr(QueryBuilder $queryBuilder, $field, $fieldValue)
    {
        $fieldID = str_replace(".", "_", implode("_", $field));
        $fieldName = $this->getAlias($field[0]);
        $fieldRoot = implode(self::SEPARATOR, array_slice($field, count($field) - 2, 2));
        $fieldHead = explode(".", $fieldName)[0];

        $isNull = $this->findCustomOption($fieldRoot, self::OPTION_NULL);
        $isNotNull = $this->findCustomOption($fieldRoot, self::OPTION_NOT_NULL);
        $isEmpty = $this->findCustomOption($fieldRoot, self::OPTION_EMPTY);
        $isNotEmpty = $this->findCustomOption($fieldRoot, self::OPTION_NOT_EMPTY);
        $isBool = $this->findCustomOption($fieldRoot, self::OPTION_TRUE) ||
            $this->findCustomOption($fieldRoot, self::OPTION_NOT_TRUE) ||
            $this->findCustomOption($fieldRoot, self::OPTION_FALSE) ||
            $this->findCustomOption($fieldRoot, self::OPTION_NOT_FALSE);

        $isPartial = $this->findCustomOption($fieldRoot, self::OPTION_PARTIAL);
        $isInsensitive = $this->findCustomOption($fieldRoot, self::OPTION_INSENSITIVE);
        $closestTo = $this->findCustomOption($fieldRoot, self::OPTION_CLOSESTTO);
        $farestTo = $this->findCustomOption($fieldRoot, self::OPTION_FARESTTO);

        $isMemberOf = $this->findCustomOption($fieldRoot, self::OPTION_MEMBEROF);
        $isNotMemberOf = $this->findCustomOption($fieldRoot, self::OPTION_NOT_MEMBEROF);
        $isInstanceOf = $this->findCustomOption($fieldRoot, self::OPTION_INSTANCEOF);
        $isNotInstanceOf = $this->findCustomOption($fieldRoot, self::OPTION_NOT_INSTANCEOF);
        $isClassOf = $this->findCustomOption($fieldRoot, self::OPTION_CLASSOF);
        $isNotClassOf = $this->findCustomOption($fieldRoot, self::OPTION_NOT_CLASSOF);


        $tableOperator = self::OPTION_EQUAL;
        if ($this->findCustomOption($fieldRoot, self::OPTION_INSTANCEOF)) {
            $tableOperator = self::OPTION_INSTANCEOF;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NOT_INSTANCEOF)) {
            $tableOperator = self::OPTION_NOT_INSTANCEOF;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_CLASSOF)) {
            $tableOperator = self::OPTION_CLASSOF;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NOT_CLASSOF)) {
            $tableOperator = self::OPTION_NOT_CLASSOF;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_MEMBEROF)) {
            $tableOperator = self::OPTION_MEMBEROF;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NOT_MEMBEROF)) {
            $tableOperator = self::OPTION_NOT_MEMBEROF;
        } // Datetime related options
        elseif ($this->findCustomOption($fieldRoot, self::OPTION_OVER)) {
            $tableOperator = self::OPTION_OVER;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NOT_OVER)) {
            $tableOperator = self::OPTION_NOT_OVER;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_OLDER)) {
            $tableOperator = self::OPTION_OLDER;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_OLDER_EQUAL)) {
            $tableOperator = self::OPTION_OLDER_EQUAL;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_WITHIN)) {
            $tableOperator = self::OPTION_WITHIN;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_YOUNGER)) {
            $tableOperator = self::OPTION_YOUNGER;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_YOUNGER_EQUAL)) {
            $tableOperator = self::OPTION_YOUNGER_EQUAL;
        } // String related options
        elseif ($this->findCustomOption($fieldRoot, self::OPTION_STARTING_WITH)) {
            $tableOperator = self::OPTION_STARTING_WITH;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_ENDING_WITH)) {
            $tableOperator = self::OPTION_ENDING_WITH;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NOT_STARTING_WITH)) {
            $tableOperator = self::OPTION_NOT_STARTING_WITH;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NOT_ENDING_WITH)) {
            $tableOperator = self::OPTION_NOT_ENDING_WITH;
        } // Number related options
        elseif ($this->findCustomOption($fieldRoot, self::OPTION_GREATER)) {
            $tableOperator = self::OPTION_GREATER;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_GREATER_EQUAL)) {
            $tableOperator = self::OPTION_GREATER_EQUAL;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_LOWER)) {
            $tableOperator = self::OPTION_LOWER;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_LOWER_EQUAL)) {
            $tableOperator = self::OPTION_LOWER_EQUAL;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NULL)) {
            $tableOperator = self::OPTION_NULL;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NOT_NULL)) {
            $tableOperator = self::OPTION_NOT_NULL;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_EMPTY)) {
            $tableOperator = self::OPTION_EMPTY;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NOT_EMPTY)) {
            $tableOperator = self::OPTION_NOT_EMPTY;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_TRUE)) {
            $tableOperator = self::OPTION_TRUE;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NOT_TRUE)) {
            $tableOperator = self::OPTION_NOT_TRUE;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_FALSE)) {
            $tableOperator = self::OPTION_FALSE;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NOT_FALSE)) {
            $tableOperator = self::OPTION_NOT_FALSE;
        } elseif ($this->findCustomOption($fieldRoot, self::OPTION_NOT_EQUAL)) {
            $tableOperator = self::OPTION_NOT_EQUAL;
        }

        switch ($tableOperator) {
            case self::OPTION_MEMBEROF:
            case self::OPTION_NOT_MEMBEROF:
            case self::OPTION_INSTANCEOF:
            case self::OPTION_NOT_INSTANCEOF:
            case self::OPTION_CLASSOF:
            case self::OPTION_NOT_CLASSOF:
                $tableColumn = self::ALIAS_ENTITY;
                break;

            default:

                if ($this->classMetadata->hasAssociation($fieldHead)) {
                    $tableColumn = self::ALIAS_ENTITY . "_" . $fieldName;
                } elseif ($this->classMetadata->hasField($fieldHead)) {
                    $tableColumn = self::ALIAS_ENTITY . "." . $fieldName;
                } else {
                    $tableColumn = $fieldName;
                }
        }

        $datetimeRequested = in_array($tableOperator, [self::OPTION_OVER, self::OPTION_NOT_OVER, self::OPTION_OLDER, self::OPTION_WITHIN, self::OPTION_OLDER_EQUAL, self::OPTION_YOUNGER, self::OPTION_YOUNGER_EQUAL]);
        if ($datetimeRequested) {
            if (is_numeric($fieldValue)) {
                $fieldValue = ($fieldValue > 0 ? "+" : "-") . $fieldValue . " second" . ($fieldValue > 1 ? "s" : "");
            }

            if ($fieldValue instanceof DateTime && in_array($tableOperator, [self::OPTION_YOUNGER, self::OPTION_YOUNGER_EQUAL, self::OPTION_OLDER, self::OPTION_OLDER_EQUAL])) {
                throw new Exception("Please use string or int with Older/Younger operands");
            }

            if (in_array($tableOperator, [self::OPTION_OVER, self::OPTION_NOT_OVER])) {
                $fieldValue = new DateTime("now");
            } elseif ($this->validateDate($fieldValue) && !$fieldValue instanceof DateTime) {
                $fieldValue = new DateTime($fieldValue);
            } elseif (!$fieldValue instanceof DateTime) {
                // An age (YoungerThan, OlderThan: "+2 days", 172800) is counted back from now;
                // Within's offset is counted forward. The bound is rounded to the minute, so a
                // cached query (the cache* finders) keeps its key for that minute. It used to be
                // round_datetime(now, $age): now rounded to a multiple of the age, an instant at
                // or after now - YoungerThan matched nothing and OlderThan nearly everything.
                $offset = $fieldValue;
                if (in_array($tableOperator, [self::OPTION_YOUNGER, self::OPTION_YOUNGER_EQUAL, self::OPTION_OLDER, self::OPTION_OLDER_EQUAL])) {
                    $offset = strtr($fieldValue, ["+" => "-", "-" => "+"]);
                }

                $fieldValue = round_datetime((new DateTime("now"))->modify($offset), "+1 minute");
            }
        }

        $regexRequested = in_array($tableOperator, [self::OPTION_STARTING_WITH, self::OPTION_ENDING_WITH, self::OPTION_NOT_STARTING_WITH, self::OPTION_NOT_ENDING_WITH]);

        if ($this->classMetadataManipulator->getTypeOfField($this->classMetadata, $fieldName) == "json") {

            if (is_array($fieldValue)) {

                if (empty($fieldValue)) {
                    return $queryBuilder->expr()->eq(1, 1);
                }

                $queryExpr = [];
                foreach ($fieldValue as $subFieldID => $subFieldValue) {

                    if ($isInsensitive) {
                        $queryExpr[] = $queryBuilder->expr()->eq("JSON_CONTAINS(LOWER(JSON_EXTRACT(" . self::ALIAS_ENTITY . "." . $fieldName . ", '$')), LOWER(:" . $fieldID . "_" . $subFieldID . "), '$')", "1");
                    } else {
                        $queryExpr[] = $queryBuilder->expr()->eq("JSON_CONTAINS(" . self::ALIAS_ENTITY . "." . $fieldName . ", :" . $fieldID . "_" . $subFieldID . ", '$')", "1");
                    }

                    $queryBuilder->setParameter(":" . $fieldID . "_" . $subFieldID, '"' . $subFieldValue . '"');
                }

                if ($tableOperator == self::OPTION_EQUAL) {
                    return $queryBuilder->expr()->andX(...$queryExpr);
                } elseif ($tableOperator == self::OPTION_NOT_EQUAL) {
                    return $queryBuilder->expr()->not($queryBuilder->expr()->andX(...$queryExpr));
                }

                throw new Exception("Invalid operator for field \"$fieldName\": " . $tableOperator);

            } elseif ($isEmpty || $isNotEmpty) {

                $fnExpr = $tableOperator == self::OPTION_EMPTY ? "eq" : "neq";
                $queryBuilder->expr()->$fnExpr("JSON_LENGTH(" . self::ALIAS_ENTITY . "." . $fieldName . ", :" . $fieldID . ", '$')", "0");

            } else {

                if ($isInsensitive) {
                    return $queryBuilder->expr()->eq("JSON_CONTAINS(JSON_EXTRACT(LOWER(" . self::ALIAS_ENTITY . "." . $fieldName . "), '$.NODE'), LOWER(:" . $fieldID . "), '$')", "1");
                } else {
                    return $queryBuilder->expr()->eq("JSON_CONTAINS(" . self::ALIAS_ENTITY . "." . $fieldName . ", :" . $fieldID . ", '$')", "1");
                }
            }

            throw new \LogicException("Operation not supported for json-like field \"" . $fieldName . "\" in \"" . $this->classMetadata->getName() . "\"");

        } elseif ($regexRequested) {

            $fieldValue = str_replace(["_", "\%"], ["\_", "\%"], $fieldValue);

            if ($tableOperator == self::OPTION_STARTING_WITH) {
                $fieldValue = $fieldValue . "%";
            } elseif ($tableOperator == self::OPTION_ENDING_WITH) {
                $fieldValue = "%" . $fieldValue;
            } elseif ($tableOperator == self::OPTION_NOT_STARTING_WITH) {
                $fieldValue = $fieldValue . "%";
            } elseif ($tableOperator == self::OPTION_NOT_ENDING_WITH) {
                $fieldValue = "%" . $fieldValue;
            }

            $fieldValue = ($isInsensitive ? mb_strtolower($fieldValue) : $fieldValue);
        }

        //
        // Standard field..
        if ($isInstanceOf || $isNotInstanceOf) {

            // Cast to array
            if (!is_array($fieldValue)) {
                $fieldValue = $fieldValue !== null ? [$fieldValue] : [];
            }

            $instanceOf = [];
            $notInstanceOf = [];
            if ($this->classMetadata->discriminatorColumn !== null) {
                foreach ($fieldValue as $value) {
                    $reverseAssert = str_starts_with($value, "^");
                    if ($reverseAssert) {
                        $value = ltrim($value, "^");
                    }

                    $realValue = $this->getRealClassName($value);
                    if ($realValue === null) {
                        throw new EntityNotFoundException("Entity \"$value\" doesn't exists");
                    }

                    if ($isInstanceOf) {
                        if ($reverseAssert) {
                            $notInstanceOf[] = $queryBuilder->expr()->not($queryBuilder->expr()->isInstanceOf($tableColumn, ltrim($realValue, "^")));
                        } else {
                            $instanceOf[] = $queryBuilder->expr()->isInstanceOf($tableColumn, $realValue);
                        }
                    } else {
                        if ($reverseAssert) {
                            $instanceOf[] = $queryBuilder->expr()->isInstanceOf($tableColumn, ltrim($realValue, "^"));
                        } else {
                            $notInstanceOf[] = $queryBuilder->expr()->not($queryBuilder->expr()->isInstanceOf($tableColumn, $realValue));
                        }
                    }
                }
            }

            $instanceOf = $instanceOf ? [$queryBuilder->expr()->orX(...$instanceOf)] : [];
            if ($notInstanceOf) {
                $instanceOf[] = $queryBuilder->expr()->andX(...$notInstanceOf);
            }

            return $instanceOf ? $queryBuilder->expr()->andX(...$instanceOf) : "";

        } elseif ($isClassOf || $isNotClassOf) {

            // Cast to array
            if (!is_array($fieldValue)) {
                $fieldValue = $fieldValue !== null ? [$fieldValue] : [];
            }

            $classOf = [];
            $notClassOf = [];
            if ($this->classMetadata->discriminatorColumn !== null) {
                foreach ($fieldValue as $value) {

                    $reverseAssert = str_starts_with($value, "^");
                    if ($reverseAssert) {
                        $value = ltrim($value, "^");
                    }

                    $realValue = $this->getRealClassName($value);
                    if ($realValue === null) {
                        throw new EntityNotFoundException("Entity \"$value\" doesn't exists");
                    }

                    $classMetadata = $this->entityManager->getClassMetadata($realValue);
                    $childClass = array_filter(array_values($classMetadata->discriminatorMap), fn($c) => $c != $realValue && is_instanceof($c, $realValue));

                    if ($isClassOf) {

                        if (!$reverseAssert) {

                            $queryPart = [$queryBuilder->expr()->isInstanceOf($tableColumn, $realValue)];
                            foreach ($childClass as $child) {
                                $queryPart[] = $queryBuilder->expr()->not($queryBuilder->expr()->isInstanceOf($tableColumn, $child));
                            }

                            $classOf[] = $queryBuilder->expr()->andX(...$queryPart);
                        } else {
                            $queryPart = [$queryBuilder->expr()->not($queryBuilder->expr()->isInstanceOf($tableColumn, $realValue))];
                            foreach ($childClass as $child) {
                                $queryPart[] = $queryBuilder->expr()->isInstanceOf($tableColumn, $child);
                            }

                            $classOf[] = $queryBuilder->expr()->orX(...$queryPart);
                        }

                    } else {

                        // NotClassOf: anything but that class exactly (its subclasses included); with ^, that class exactly.
                        if (!$reverseAssert) {

                            $queryPart = [$queryBuilder->expr()->not($queryBuilder->expr()->isInstanceOf($tableColumn, $realValue))];
                            foreach ($childClass as $child) {
                                $queryPart[] = $queryBuilder->expr()->isInstanceOf($tableColumn, $child);
                            }

                            $notClassOf[] = $queryBuilder->expr()->orX(...$queryPart);
                        } else {
                            $queryPart = [$queryBuilder->expr()->isInstanceOf($tableColumn, $realValue)];
                            foreach ($childClass as $child) {
                                $queryPart[] = $queryBuilder->expr()->not($queryBuilder->expr()->isInstanceOf($tableColumn, $child));
                            }

                            $classOf[] = $queryBuilder->expr()->andX(...$queryPart);
                        }
                    }
                }
            }

            $classOf = $classOf ? [$queryBuilder->expr()->orX(...$classOf)] : [];
            if ($notClassOf) {
                $classOf[] = $queryBuilder->expr()->andX(...$notClassOf);
            }

            return $classOf ? $queryBuilder->expr()->andX(...$classOf) : "";

        } elseif ($isMemberOf || $isNotMemberOf) {

            // Cast to array
            if (!is_array($fieldValue)) {
                $fieldValue = $fieldValue !== null ? [$fieldValue] : [];
            }

            $memberOf = [];
            $notMemberOf = [];
            if ($this->classMetadata->discriminatorColumn !== null) {
                foreach ($fieldValue as $value) {
                    $realValue = $this->getRealClassName($value);
                    if ($realValue === null) {
                        throw new EntityNotFoundException("Entity \"$value\" doesn't exists");
                    }

                    if ($isMemberOf) {
                        $memberOf[] = $queryBuilder->expr()->isMemberOf($tableColumn, $realValue);
                    } else {
                        $notMemberOf[] = $queryBuilder->expr()->not($queryBuilder->expr()->isMemberOf($tableColumn, $realValue));
                    }
                }
            }

            $memberOf = $memberOf ? [$queryBuilder->expr()->orX(...$memberOf)] : [];
            if ($notMemberOf) {
                $memberOf[] = $queryBuilder->expr()->andX(...$notMemberOf);
            }

            return $memberOf ? $queryBuilder->expr()->andX(...$memberOf) : "";
        } else {

            if ($this->classMetadata->hasAssociation($fieldHead)) {

                // NB:: Still useful for select.. to be improved (fetch owning side ?)
                // if($this->classMetadata->isAssociationInverseSide($fieldHead))
                //     throw new Exception("Association \"$fieldHead\" for \"".$this->classMetadata->getName()."\" is not owning side");

                $fieldID = self::ALIAS_ENTITY . "_" . $fieldID;

                if ($isPartial) { // PARTIAL HAS TO BE CHECKED SINCE THE UPDATE.. NOT TESTED
                    // Cast to array
                    $this->leftJoin($queryBuilder, self::ALIAS_ENTITY . "." . $fieldHead);

                    if (!is_array($fieldValue)) {
                        $fieldValue = ($fieldValue !== null) ? [$fieldValue] : [];
                    }
                    foreach ($fieldValue as $subFieldID => $subFieldValue) {
                        $queryBuilder->setParameter($fieldID . "_" . $subFieldID, $subFieldValue);
                    }
                } else {

                    if (!is_array($fieldValue)) {

                        $this->leftJoin($queryBuilder, self::ALIAS_ENTITY . "." . $fieldHead);

                        // Null, NotNull, Empty, NotEmpty and the booleans compare with no parameter of their own.
                        if (!$isEmpty && !$isNotEmpty && !$isBool && !$isNull && !$isNotNull) {
                            $queryBuilder->setParameter($fieldID, $this->boundValueOf($fieldHead, $fieldValue), $this->dateTypeOf($fieldHead, $fieldValue));
                        }
                    } else {

                        $fieldValue = array_filter($fieldValue);
                        if ($fieldValue) {
                            $this->leftJoin($queryBuilder, self::ALIAS_ENTITY . "." . $fieldHead);

                            $queryBuilder->setParameter($fieldID, $fieldValue);
                        }
                    }
                }

            } elseif (is_array($fieldValue)) {

                if (!empty($fieldValue)) {
                    $queryBuilder->setParameter($fieldID, $fieldValue);
                }

            } elseif (!$isEmpty && !$isNotEmpty && !$isBool && !$isNull && !$isNotNull) {
                $queryBuilder->setParameter($fieldID, $this->boundValueOf($fieldHead, $fieldValue), $this->dateTypeOf($fieldHead, $fieldValue));
            }
            
            if ($isInsensitive) {
                $tableColumn = "LOWER(" . $tableColumn . ")";
            }

            if ($isPartial) {

                if ($tableOperator != self::OPTION_EQUAL && $tableOperator != self::OPTION_NOT_EQUAL) {
                    throw new Exception("Invalid operator for association field \"$fieldName\": " . $tableOperator);
                }

                $queryExpr = [];
                $fnExpr = ($tableOperator == self::OPTION_EQUAL ? "like" : "notLike");

                if (!is_array($fieldValue)) {
                    $queryExpr[] = $queryBuilder->expr()->$fnExpr($tableColumn, ":$fieldID");
                } else {
                    foreach ($fieldValue as $subFieldID => $_) {
                        $queryExpr[] = $queryBuilder->expr()->$fnExpr($fieldID, ":{$fieldID}_$subFieldID");
                    }
                }

                $fnExpr = ($tableOperator == self::OPTION_EQUAL ? "orX" : "andX");
                return $queryBuilder->expr()->$fnExpr(...$queryExpr);

            } elseif (is_array($fieldValue)) {

                if ($tableOperator == self::OPTION_EQUAL) {
                    $fnExpr = "in";
                } elseif ($tableOperator == self::OPTION_NOT_EQUAL) {
                    $fnExpr = "notIn";
                } else {
                    throw new Exception("Invalid operator for field \"$fieldName\": " . $tableOperator);
                }

                if (empty($fieldValue)) {
                    return $queryBuilder->expr()->eq(1, 1);
                }

                return $queryBuilder->expr()->$fnExpr($tableColumn, ":$fieldID");

            } elseif ($regexRequested) {

                if ($tableOperator == self::OPTION_STARTING_WITH) {
                    $fnExpr = "like";
                } elseif ($tableOperator == self::OPTION_ENDING_WITH) {
                    $fnExpr = "like";
                } elseif ($tableOperator == self::OPTION_NOT_STARTING_WITH) {
                    $fnExpr = "notLike";
                } elseif ($tableOperator == self::OPTION_NOT_ENDING_WITH) {
                    $fnExpr = "notLike";
                }

                return $queryBuilder->expr()->$fnExpr($tableColumn, ":$fieldID");
            } elseif ($closestTo || $farestTo) {
                return $queryBuilder->expr()->abs($tableColumn . " - :" . $fieldID);
            } elseif ($isNull || $isNotNull) {
                if ($tableOperator == self::OPTION_NULL) {
                    $fnExpr = "isNull";
                } elseif ($tableOperator == self::OPTION_NOT_NULL) {
                    $fnExpr = "isNotNull";
                } else {
                    throw new Exception("Invalid operator for field \"$fieldName\": " . $tableOperator);
                }

                return $queryBuilder->expr()->$fnExpr($tableColumn);
            
            } elseif ($isEmpty || $isNotEmpty) {

                if ($tableOperator == self::OPTION_EMPTY) {
                    
                    $queryExpr = [];
                    $queryExpr[] = $queryBuilder->expr()->isNull($tableColumn);
                    $queryExpr[] = $queryBuilder->expr()->eq($tableColumn, "''");

                    return $queryBuilder->expr()->orX(...$queryExpr);

                } elseif ($tableOperator == self::OPTION_NOT_EMPTY) {

                    $queryExpr = [];
                    $queryExpr[] = $queryBuilder->expr()->isNotNull($tableColumn);
                    $queryExpr[] = $queryBuilder->expr()->neq($tableColumn, "''");

                    return $queryBuilder->expr()->andX(...$queryExpr);
                }

                throw new Exception("Invalid operator for field \"$fieldName\": " . $tableOperator);
            
            } elseif ($isBool) {
            
                if ($tableOperator == self::OPTION_TRUE || $tableOperator == self::OPTION_NOT_FALSE) {
                    $fnExpr = "eq";
                } elseif ($tableOperator == self::OPTION_FALSE || $tableOperator == self::OPTION_NOT_TRUE) {
                    $fnExpr = "neq";
                } else {
                    throw new Exception("Invalid operator for field \"$fieldName\": " . $tableOperator);
                }

                return $queryBuilder->expr()->$fnExpr($tableColumn, true);

            } else {

                if ($tableOperator == self::OPTION_EQUAL) {
                    $fnExpr = "eq";
                } elseif ($tableOperator == self::OPTION_NOT_EQUAL) {
                    $fnExpr = "neq";
                } elseif ($tableOperator == self::OPTION_GREATER) {
                    $fnExpr = "gt";
                } elseif ($tableOperator == self::OPTION_GREATER_EQUAL) {
                    $fnExpr = "gte";
                } elseif ($tableOperator == self::OPTION_LOWER) {
                    $fnExpr = "lt";
                } elseif ($tableOperator == self::OPTION_LOWER_EQUAL) {
                    $fnExpr = "lte";
                } elseif ($tableOperator == self::OPTION_YOUNGER) {
                    $fnExpr = "gt";
                } elseif ($tableOperator == self::OPTION_YOUNGER_EQUAL) {
                    $fnExpr = "gte";
                } elseif ($tableOperator == self::OPTION_OVER) {
                    $fnExpr = "lt";
                } elseif ($tableOperator == self::OPTION_NOT_OVER) {
                    $fnExpr = "gt";
                } elseif ($tableOperator == self::OPTION_OLDER) {
                    $fnExpr = "lt";
                } elseif ($tableOperator == self::OPTION_OLDER_EQUAL) {
                    $fnExpr = "lte";
                } elseif ($tableOperator == self::OPTION_WITHIN) {
                    $fnExpr = "lt";
                } else {
                    throw new Exception("Invalid operator for field \"$fieldName\": " . $tableOperator);
                }

                return $queryBuilder->expr()->$fnExpr($tableColumn, ":$fieldID");
            }
        }

        throw new Exception("Failed to build expression \"" . $field . "\": " . $fieldValue);
    }

    protected static int $i = 0;

    /**
     * @param array $criteria
     * @param array $orderBy
     * @param $limit
     * @param $offset
     * @param array $groupBy
     * @param array $selectAs
     * @return QueryBuilder|null
     * @throws EntityNotFoundException
     */
    protected function getQueryBuilder(array $criteria = [], array $orderBy = [], $limit = null, $offset = null, array $groupBy = [], array $selectAs = []): ?QueryBuilder
    {
        /**
         * @QueryBuilder
         */
        $queryBuilder = $this->serviceEntity
            ->createQueryBuilder(self::ALIAS_ENTITY)
            ->setMaxResults($limit ?? null)
            ->setFirstResult($offset ?? null)
            ->setCacheable($this->cacheable);

        $this->joinList[spl_object_hash($queryBuilder)] = [];

        // Prepare criteria variable
        foreach ($criteria as $field => $fieldValue) {
            $field = explode(self::SEPARATOR, $field);
            $fieldName = $field[0];

            if ($fieldValue instanceof PersistentCollection) {
                throw new Exception("You passed a PersistentCollection for field \"" . $fieldName . "\"");
            }

            // Handle partial entity/model input criteria
            if ($fieldName == lcfirst(self::OPTION_MODEL)) {
                $queryExpr = [];
                foreach ($fieldValue ?? [] as $entryID => $entryValue) {
                    $newField = [];
                    foreach ($field as $key => $value) {
                        $newField[$key] = $value;
                    }

                    array_unshift($newField, $entryID);

                    $queryExprPart = $this->buildQueryExpr($queryBuilder, $newField, $entryValue);
                    if ($queryExprPart == null) {
                        continue;
                    }

                    // In case of association field, compare value directly
                    if ($this->classMetadata->hasAssociation($entryID)) {
                        $queryBuilder->andWhere($queryExprPart);
                    } // If standard field, check for partial information
                    else {
                        $queryExpr[] = $queryExprPart;
                    }
                }

                // Query-by-Example: the select's set fields are AND-combined.
                $queryExpr = empty($queryExpr) ? null : $queryBuilder->expr()->andX(...$queryExpr);
            } else {
                // Default query builder
                $queryExpr = $this->buildQueryExpr($queryBuilder, $field, $fieldValue);
                if ($queryExpr == null) {
                    continue;
                }

                // Custom process in case of closest/farest
                $fieldRoot = implode(self::SEPARATOR, array_slice($field, count($field) - 2, 2));
                $closestTo = $this->findCustomOption($fieldRoot, self::OPTION_CLOSESTTO);
                $farestTo = $this->findCustomOption($fieldRoot, self::OPTION_FARESTTO);
                if ($closestTo || $farestTo) {
                    $orderBy[(string)$queryExpr] = $closestTo ? "ASC" : "DESC";
                    $queryExpr = null;
                }
            }

            if ($queryExpr !== null) {
                // Apply logical operator (if needed)
                $separator = $this->getSeparator();
                switch ($separator) {
                    case self::SEPARATOR_OR:
                        $queryBuilder->orWhere($queryExpr);
                        break;

                    case self::SEPARATOR_AND:
                        $queryBuilder->andWhere($queryExpr);
                        break;

                    default:
                        throw new Exception("Unknown separator \"" . $separator . "\" provided");
                }
            }
        }

        // Ordering result by group
        if ($groupBy) {
            $queryBuilder->select(self::ALIAS_ENTITY . " AS entity");
        } else {
            $queryBuilder->select(self::ALIAS_ENTITY);
        }
        
        $queryBuilder = $this->selectAs($queryBuilder, $selectAs);
        $queryBuilder = $this->orderBy($queryBuilder, $orderBy);
        $queryBuilder = $this->groupBy($queryBuilder, $groupBy);

        return $queryBuilder;
    }

    protected function getEagerQuery(QueryBuilder $queryBuilder, array $options = [], ?ClassMetadata $classMetadata = null): Query
    {
        $aliasRoot = $options["alias"] ?? self::ALIAS_ENTITY;
        $required = $options["required"] ?? false;

        $depth = $options["depth"] ?? 4;
        if ($depth-- < 1) {
            return $queryBuilder->getQuery();
        }

        $joinList = $options["join"] ?? array_combine($this->eagerly ?? [], array_fill(0, count($this->eagerly ?? []), []));
        $joinList = array_key_removes_numerics(array_inflate(".", $joinList));

        if ($classMetadata === null) {
            $classMetadata = $this->classMetadata;
        }

        foreach ($classMetadata->getAssociationMappings() as $associationMapping) {
            if ($associationMapping["fetch"] == ClassMetadata::FETCH_EAGER) {
                continue;
            }
            $aliasExpr = $aliasRoot . "." . $associationMapping["fieldName"];
            $aliasIdentifier = str_replace(".", "_", $aliasExpr);

            $continue = false;
            $expressions = $queryBuilder->getDQLParts()["select"];
            foreach ($expressions as $expr) {
                $continue = in_array($aliasIdentifier, $expr->getParts());
                if ($continue) {
                    break;
                }
            }

            if ($continue) {
                continue;
            }

            $expressions = $queryBuilder->getDQLParts()["join"][$aliasExpr] ?? [];
            foreach ($expressions as $expr) {
                $continue = $expr->getAlias() == $aliasIdentifier;
                if ($continue) {
                    break;
                }
            }

            if ($continue) {
                continue;
            }

            $sourceEntity = $associationMapping["sourceEntity"];
            $targetEntity = $associationMapping["targetEntity"];

            $targetEntityCacheable = $this->entityManager->getClassMetadata($targetEntity)->cache != null;
            while ($targetEntityCacheable && $targetEntity = get_parent_class($targetEntity)) {
                if (!$this->classMetadataManipulator->isEntity($targetEntity)) {
                    break;
                }
                $targetEntityCacheable &= $this->entityManager->getClassMetadata($targetEntity)->cache != null;
            }

            if ($required && !$targetEntityCacheable) {
                throw new Exception("\"" . $sourceEntity . "\" cannot be cached eagerly because of target entity is not configured as a second level cache.");
            }

            // if (class_implements_interface($sourceEntity, TranslatableInterface::class) && $associationMapping["fieldName"] == TranslatableWalker::COLUMN_NAME) {
            //     continue;
            // } // This is to make sure Translations are not eagerly loaded... see @WARN below.

            $targetEntity = $associationMapping["targetEntity"];
            if ($targetEntityCacheable && array_key_exists($associationMapping["fieldName"], $joinList)) {

                $this->leftJoin($queryBuilder, $aliasExpr);
                $queryBuilder->addSelect($aliasIdentifier);

                // $newOptions = [
                //     "alias" => $aliasIdentifier,
                //     "required" => $required,
                //     "depth" => $depth,
                //     "join" => $joinList[$associationMapping["fieldName"]]
                // ];

                // Map associations (eagerly loaded) to the query builder to make sure they are cached
                // $targetClassMetadata = $this->entityManager->getClassMetadata($targetEntity);
                // $this->getEagerQuery($queryBuilder, array_merge($options, $newOptions), $targetClassMetadata);
            }
        }

        return $queryBuilder->getQuery();
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param $limit
     * @param $offset
     * @param array|null $groupBy
     * @param array|null $selectAs
     * @return Query|null
     * @throws Exception
     */
    protected function getQuery(array $criteria = [], ?array $orderBy = null, $limit = null, $offset = null, ?array $groupBy = null, ?array $selectAs = null): ?Query
    {
        $queryBuilder = $this->getQueryBuilder($criteria, $orderBy ?? [], $limit, $offset, $groupBy ?? [], $selectAs ?? []);

        $dispatcher = $this->entityManager->getEventManager();
        if ($dispatcher->hasListeners(Events::preQuery)) {
            $eventArgs = new DoctrineQueryEventArgs($this->classMetadata, $queryBuilder, null);
            $dispatcher->dispatchEvent(Events::preQuery, $eventArgs);
            $queryBuilder = $eventArgs->getQueryBuilder();
        }

        $query = $this->eagerly === false ? $queryBuilder->getQuery() : $this->getEagerQuery($queryBuilder);
        if ($dispatcher->hasListeners(Events::onQuery)) {
            $eventArgs = new DoctrineQueryEventArgs($this->classMetadata, $queryBuilder, $query);
            $dispatcher->dispatchEvent(Events::onQuery, $eventArgs);
            $query = $eventArgs->getQuery();
        }        

        // @WARN, if groupBy is used, cache must be disabled.. id column not stored for some reasons.
        if ($groupBy) $query->setCacheable(false);

        $query->useQueryCache($this->cacheable);
    	if($this->classMetadata->cache !== null && array_key_exists("region", $this->classMetadata->cache)) {
            $query->setCacheRegion($this->classMetadata->cache["region"]);
        }

        if ($dispatcher->hasListeners(Events::postQuery)) {
            $eventArgs = new DoctrineQueryEventArgs($this->classMetadata, null, $query);
            $dispatcher->dispatchEvent(Events::postQuery, $eventArgs);
            $query = $eventArgs->getQuery();
        }

        return $query;
    }

    /**
     * @param array $criteria
     * @param string|null $mode
     * @param array|null $orderBy
     * @param array|null $groupBy
     * @param array|null $selectAs
     * @return Query
     * @throws Exception
     */
    protected function getQueryWithCount(array $criteria = [], ?string $mode = self::COUNT_ALL, ?array $orderBy = null, ?array $groupBy = null, ?array $selectAs = null)
    {
        if ($mode == self::COUNT_ALL) {
            $mode = "";
        }
        if ($mode && $mode != self::COUNT_DISTINCT) {
            throw new Exception("Unexpected \"mode\" provided: \"" . $mode . "\"");
        }

        $column = $this->getAlias($this->getColumn());
        if (!$column) {
            $column = "id";
        }

        $e_column = $column;
        if ($this->classMetadata->hasAssociation($column)) {
            $e_column = self::ALIAS_ENTITY . "_" . $e_column;
        } elseif ($this->classMetadata->hasField($column)) {
            $e_column = self::ALIAS_ENTITY . "." . $e_column;
        }

        $grouped = !empty($groupBy) || !empty($selectAs);

        $queryBuilder = $this->getQueryBuilder($criteria, $grouped ? ($orderBy ?? []) : [], null, null, $groupBy ?? $selectAs ?? [], $selectAs ?? []);
        if ($this->classMetadata->hasAssociation($column)) {
            $this->leftJoin($queryBuilder, self::ALIAS_ENTITY . "." . $column);
        }

        if (!$grouped) {
            // One number: COUNT() alone, neither the entity nor a GROUP BY
            // (grouped by the counted column, each row counted itself).
            $queryBuilder->select('COUNT(' . trim($mode . ' ' . $e_column) . ')')->resetDQLPart('orderBy');
            return $queryBuilder->getQuery();
        }

        $queryBuilder->addSelect('COUNT(' . trim($mode . ' ' . $e_column) . ') AS count');
        $queryBuilder->addGroupBy($e_column);

        return $queryBuilder->getQuery();
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param $limit
     * @param $offset
     * @param array|null $groupBy
     * @param array|null $selectAs
     * @return Query
     * @throws Exception
     */
    protected function getQueryWithLength(array $criteria = [], ?array $orderBy = null, $limit = null, $offset = null, ?array $groupBy = null, ?array $selectAs = null)
    {
        $column = $this->getAlias($this->getColumn());

        $queryBuilder = $this->getQueryBuilder($criteria, $orderBy ?? [], $limit, $offset, $groupBy ?? $selectAs ?? [], $selectAs ?? []);

        // A field's length is LENGTH() of its value; a collection's is its number of members, SIZE()
        // (lengthOfTagsBy...: LENGTH() of the joined alias was no DQL).
        if ($this->classMetadata->hasAssociation($column) && $this->classMetadata->isCollectionValuedAssociation($column)) {
            $queryBuilder->addSelect("SIZE(" . self::ALIAS_ENTITY . "." . $column . ") as length");
        } else {
            $e_column = $this->classMetadata->hasField($column) ? self::ALIAS_ENTITY . "." . $column : $column;
            $queryBuilder->addSelect("LENGTH(" . $e_column . ") as length");
        }

        return $queryBuilder->getQuery();
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param $selectAs
     * @return QueryBuilder
     */
    protected function selectAs(QueryBuilder $queryBuilder, $selectAs)
    {
        if (!$selectAs) {
            return $queryBuilder;
        }

        foreach ($selectAs as $select => $as) {
            $queryBuilder->addSelect(is_string($select) ? $as . " AS " . $select : $as);
        }

        return $queryBuilder;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param $groupBy
     * @return QueryBuilder
     * @throws Exception
     */
    protected function groupBy(QueryBuilder $queryBuilder, $groupBy)
    {
        if (!$groupBy) {
            return $queryBuilder;
        }

        $column = explode("\\", $this->classMetadata->getName());
        $column = lcfirst(end($column));
        $column = $this->getAlias($column);

        if (is_string($groupBy)) {
            $groupBy = [$groupBy];
        }
        if (!is_array($groupBy)) {
            throw new Exception("Unexpected \"groupBy\" argument type provided \"" . gettype($groupBy) . "\"");
        }

        foreach ($groupBy as $key => $column) {
            $aliasIdentifier = str_replace(".", "_", $column);

            $column = implode(".", array_map(fn($c) => $this->getAlias($c), explode(".", $column)));
            $columnHead = explode(".", $column)[0] ?? $column;

            $groupBy[$key] = $column;
            if ($this->classMetadata->hasAssociation($columnHead)) {
                $groupBy[$key] = self::ALIAS_ENTITY . "_" . $groupBy[$key];
            } elseif ($this->classMetadata->hasField($columnHead)) {
                $groupBy[$key] = self::ALIAS_ENTITY . "." . $groupBy[$key];
            }

            if ($this->classMetadata->hasAssociation($column)) {
                $this->leftJoin($queryBuilder, self::ALIAS_ENTITY . "." . $column, $groupBy[$key]);
                $queryBuilder->addSelect("(" . $groupBy[$key] . ".id) AS " . $aliasIdentifier);
            }
        }

        return $queryBuilder->groupBy(implode(",", $groupBy));
    }

    protected array $joinList = [];

    /**
     * @param QueryBuilder $queryBuilder
     * @param $join
     * @param $alias
     * @param $conditionType
     * @param $condition
     * @param $indexBy
     * @return bool
     */
    protected function innerJoin(QueryBuilder $queryBuilder, $join, $alias = null, $conditionType = null, $condition = null, $indexBy = null): bool
    {
        if (in_array($join, $this->joinList[spl_object_hash($queryBuilder)] ?? [])) {
            return false;
        }

        $queryBuilder->innerJoin($join, $alias ?? str_replace(".", "_", $join), $conditionType, $condition, $indexBy);
        $this->joinList[spl_object_hash($queryBuilder)][] = $join;

        return true;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param $join
     * @param $alias
     * @param $conditionType
     * @param $condition
     * @param $indexBy
     * @return bool
     */
    protected function leftJoin(QueryBuilder $queryBuilder, $join, $alias = null, $conditionType = null, $condition = null, $indexBy = null): bool
    {
        if (in_array($join, $this->joinList[spl_object_hash($queryBuilder)] ?? [])) {
            return false;
        }

        $queryBuilder->leftJoin($join, $alias ?? str_replace(".", "_", $join), $conditionType, $condition, $indexBy);
        $this->joinList[spl_object_hash($queryBuilder)][] = $join;
        return true;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string|array|null $orderBy
     * @return QueryBuilder
     * @throws Exception
     */
    protected function orderBy(QueryBuilder $queryBuilder, string|array|null $orderBy)
    {
        if (!$orderBy) {
            return $queryBuilder;
        }

        $column = explode("\\", $this->classMetadata->getName());
        $column = lcfirst(end($column));

        if (is_string($orderBy)) {
            $orderBy = [$orderBy => "ASC"];
        }
        if (!is_array($orderBy)) {
            throw new Exception("Unexpected \"orderBy\" argument type provided \"" . gettype($orderBy) . "\"");
        }

        $first = true;
        foreach ($orderBy as $name => $value) {
            $path = array_map(fn($name) => $this->getAlias($name), explode(".", $name));
            $name = implode(".", $path);
            $entity = $path[0];

            $isRandom = ($name == "id" && strtolower($value) == "rand");
            if (!$isRandom) {
                $formattedName = $name;
                if ($this->classMetadata->hasField($entity)) {
                    $formattedName = self::ALIAS_ENTITY . "." . $name;
                } elseif ($this->classMetadata->hasAssociation($entity)) {
                    $formattedName = self::ALIAS_ENTITY . "_" . $name;
                }

                if ($this->classMetadata->hasAssociation($entity)) {
                    $this->leftJoin($queryBuilder, self::ALIAS_ENTITY . "." . $entity);
                }
            }

            $orderBy = $first ? "orderBy" : "addOrderBy";

            if ($isRandom) {
                $queryBuilder->orderBy('RAND()');
            } elseif (is_array($value)) {
                $queryBuilder->add($orderBy, "FIELD(" . $formattedName . "," . implode(",", $value) . ")");
            } else {
                $queryBuilder->$orderBy($formattedName, $value);
            }

            $first = false;
        }

        return $queryBuilder;
    }
}
