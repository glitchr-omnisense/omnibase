<?php

namespace Base\Database\Repository;

use Base\Database\Factory\EntityHydrator;
use Base\Database\TranslatableInterface;
use Base\Database\Walker\TranslatableWalker;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Exception;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;

class ServiceEntityParser
{
    protected $method;
    protected $args;

    public const ALIAS_ENTITY = 'e';
    public const ALIAS_TRANSLATIONS = 't';

    public const REQUEST_FIND         = "find";
    public const REQUEST_CACHE        = "cache";
    public const REQUEST_COUNT        = "count";
    public const REQUEST_DISTINCT     = "distinctCount";
    public const REQUEST_LENGTH       = "lengthOf";

    public const SPECIAL_ALL     = "All";
    public const SPECIAL_ONE     = "One";
    public const SPECIAL_RAND    = "Randomly";
    public const SPECIAL_ATMOST  = "AtMost(?P<atMost>[0-9])*";
    public const SPECIAL_LASTONE = "LastOne";
    public const SPECIAL_LAST    = "Last(?P<last>[0-9])*";
    public const SPECIAL_PREVONE = "PreviousOne";
    public const SPECIAL_PREV    = "Previous(?P<prev>[0-9]*)";
    public const SPECIAL_NEXTONE = "NextOne";
    public const SPECIAL_NEXT    = "Next(?P<next>[0-9]*)";

    protected static function getSpecial(string $special): string { return only_alphanumerics(trim_brackets($special)); }
    protected static function getSpecials(): array
    {
        return [self::SPECIAL_ALL , self::SPECIAL_ONE, self::SPECIAL_ATMOST, self::SPECIAL_LAST, self::SPECIAL_LASTONE,
                self::SPECIAL_RAND, self::SPECIAL_PREVONE, self::SPECIAL_NEXTONE, self::SPECIAL_PREV, self::SPECIAL_NEXT];
    }

    // Default options
    public const OPTION_EQUAL         = "EqualTo";
    public const OPTION_NOT_EQUAL     = "NotEqualTo";
    public const OPTION_GREATER       = "GreaterThan";
    public const OPTION_GREATER_EQUAL = "GreaterEqualTo";
    public const OPTION_LOWER         = "LowerThan";
    public const OPTION_LOWER_EQUAL   = "LowerEqualTo";

    // Datetime related options
    public const OPTION_OLDER         = "OlderThan";
    public const OPTION_OLDER_EQUAL   = "OlderEqualTo";
    public const OPTION_YOUNGER       = "YoungerThan";
    public const OPTION_YOUNGER_EQUAL = "YoungerEqualTo";
    public const OPTION_OVER          = "IsOver";
    public const OPTION_NOT_OVER      = "IsNotOver";

    // String related options
    public const OPTION_STARTING_WITH     = "StartingWith";
    public const OPTION_ENDING_WITH       = "EndingWith";
    public const OPTION_NOT_STARTING_WITH = "NotStartingWith";
    public const OPTION_NOT_ENDING_WITH   = "NotEndingWith";

    // Custom options
    public const OPTION_INSENSITIVE          = "Insensitive";
    public const OPTION_WITH_ROUTE           = "WithRoute";
    public const OPTION_PARTIAL              = "Partial";
    public const OPTION_MODEL                = "Model";
    public const OPTION_CLASSOF              = "ClassOf";
    public const OPTION_NOT_CLASSOF          = "NotClassOf";
    public const OPTION_INSTANCEOF           = "InstanceOf";
    public const OPTION_NOT_INSTANCEOF       = "NotInstanceOf";
    public const OPTION_MEMBEROF             = "MemberOf";
    public const OPTION_NOT_MEMBEROF         = "NotMemberOf";
    public const OPTION_BUT                  = "But";

    public const OPTION_CLOSESTTO        = "ClosestTo";
    public const OPTION_FARESTTO         = "FarestTo";

    // Separators
    public const SEPARATOR     = ":"; // Field separator
    public const SEPARATOR_OR  = "Or";
    public const SEPARATOR_AND = "And";
    public const SEPARATOR_FOR = "For";
    public const SEPARATOR_BY  = "By";

    // Count options
    public const COUNT_ALL = "ALL";
    public const COUNT_DISTINCT = "DISTINCT";

    protected $operator = null;
    protected $column = null;
    protected bool $cacheable = false;
    protected array $criteria = [];
    protected array $options  = [];

    protected $classMetadata = null;

    public function __construct(ServiceEntityRepository $serviceEntity, EntityManager $entityManager,  EntityHydrator $entityHydrator)
    {
        $this->serviceEntity  = $serviceEntity;
        $this->entityHydrator = $entityHydrator;
        $this->entityManager  = $entityManager;
        $this->classMetadata  = $entityManager->getClassMetadata($serviceEntity->getFqcnEntityName());

        $this->criteria = [];
        $this->options = [];
        $this->operator = null;

        foreach(self::getSpecials() as $special) {

            $special = self::getSpecial($special);
            if($special == self::SPECIAL_ALL) continue;

            $specialMethod = "__".self::REQUEST_FIND.$special.self::SEPARATOR_BY;
            if(!method_exists($this, $specialMethod))
                throw new Exception("Special \"$special\" option is missing its find method : ".$specialMethod);
        }
    }

    protected function __findBy         (array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null): ?Query { return $this->getQuery   ($selectAs, $criteria, $orderBy, $groupBy, $limit, $offset);   }
    protected function __findRandomlyBy (array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null): ?Query { return $this->__findBy   ($selectAs, $criteria, array_merge(["id" => "rand"], $orderBy ?? []), $groupBy, $limit, $offset); }
    protected function __findAll        (array $selectAs = [],                       $orderBy = null, $groupBy = null                               ): ?Query { return $this->__findBy   ($selectAs,        [], $orderBy, $groupBy, null, null);   }
    protected function __findOneBy      (array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null                               )         { return $this->__findBy   ($selectAs, $criteria, $orderBy, $groupBy, 1, null)->getOneOrNullResult(); }
    protected function __findLastOneBy  (array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null                               )         { return $this->__findOneBy($selectAs, $criteria, array_merge($orderBy ?? [], ['id' => 'DESC']), $groupBy, 1, null) ?? null; }
    protected function __findLastBy     (array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null                               ): ?Query
    {
        $limit = array_unshift($criteria);
        return $this->__findBy($selectAs, $criteria, array_merge($orderBy ?? [], ['id' => 'DESC']), $groupBy, $limit, null) ?? null;
    }

    protected function __findAtMostBy   (array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null                               ): ?Query
    {
        $limit = array_pop_key("special:atMost", $criteria);
        return $this->__findBy($selectAs, $criteria, $orderBy, $groupBy, $limit, null);
    }

    protected function __findPreviousBy   (array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null): ?Query
    {
        $limit = array_pop_key("special:prev", $criteria);
        $orderBy["id"] = "DESC";
        return $this->__findBy($selectAs, $criteria, $orderBy, $groupBy, $limit);
    }

    protected function __findNextBy   (array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null): ?Query
    {
        $limit = array_pop_key("special:prev", $criteria);
        return $this->__findBy($selectAs, $criteria, $orderBy, $groupBy, $limit);
    }

    protected function __findPreviousOneBy   (array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null)
    {
        $orderBy["id"] = "DESC";
        return $this->__findOneBy($selectAs, $criteria, $orderBy, $groupBy);
    }

    protected function __findNextOneBy   (array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null) { return $this->__findOneBy($selectAs, $criteria, $orderBy, $groupBy); }

    protected function __lengthOfBy     (array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null) { return $this->getQueryWithLength($selectAs, $criteria, $orderBy, $groupBy, $limit, $offset)->getResult(); }
    protected function __distinctCountBy(array $selectAs = [], array $criteria = [],                  $groupBy = null): int { return $this->__countBy($selectAs, $criteria, self::COUNT_DISTINCT, $groupBy); }
    protected function __countBy        (array $selectAs = [], array $criteria = [], ?string $mode = self::COUNT_ALL, ?array $orderBy = null, $groupBy = null)
    {
        $query = $this->getQueryWithCount($selectAs, $criteria, $mode, $orderBy, $groupBy);
        if(!$query) return null;

        $fnResult = ($groupBy ? "getResult" : "getSingleScalarResult");
        return $query->$fnResult();
    }


    public function parse($method, $arguments) : mixed
    {
        // Parse method and call it
        list($method, $arguments) = $this->__parse($method, $arguments);
        $ret = $this->$method(...$arguments);

        // Reset internal variables
        $this->criteria  = [];
        $this->options   = [];
        $this->operator  = null;
        $this->cacheable = false;

        return $ret;
    }

    protected function getAlias($alias)
    {
        $this->classMetadata->fieldNames[$alias] ?? $alias;
        return $this->classMetadata->fieldNames[$alias] ?? $alias;
    }

    protected function addCriteria(?string $by, $value)
    {
        if($by != null && empty($by))
            throw new Exception("Tried to add unnamed criteria");

        $index = 0;
        while( array_key_exists($by . self::SEPARATOR . $index, $this->criteria) )
            $index++;

        $this->criteria[$by . self::SEPARATOR . $index] = $value;
        return $by . self::SEPARATOR . $index;
    }

    protected function getColumn() { return $this->column; }
    protected function setColumn(string $column)
    {
        $this->column = $column;
        return $this;
    }

    protected function getSeparator() { return $this->operator; }
    protected function setSeparator($operator)
    {
        $this->operator = $operator;
        return $this;
    }

    protected function stripByFront($method, $by, $option)
    {
        $method = str_starts_with($method, $option) ? substr($method, strlen($option), strlen($method)) : $method;
        $by     = str_starts_with($by, $option) ? substr($by, strlen($option), strlen($by)) : $by;

        return [$method, $by];
    }

    protected function stripByEnd($method, $by, $option)
    {
        $method = str_ends_with($method, $option) ? substr($method, 0, strpos($method, $option)) : $method;
        $by     = str_ends_with($by, $option) ? substr($by, 0, strlen($by) - strlen($option)) : $by;

        return [$method, $by];
    }

    protected function validateDate($date, $format = 'Y-m-d H:i:s') {

        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    public function    getCustomOption(string $id                ) { return $this->options[$id] ?? []; }
    public function   findCustomOption(string $id, string $option) { return in_array($option, $this->getCustomOption($id)); }
    protected function addCustomOption(string $id, string $option)
    {
        if(! array_key_exists($id, $this->criteria))
            throw new Exception("Criteria ID \"$id\" not found in criteria list.. wrong usage? use \"addCriteria\" first.");

        if(!array_key_exists($id, $this->options))
            $this->options[$id] = [];

        $this->options[$id][] = $option;
    }

    protected function __parse($method, $arguments)
    {
        $method = str_strip($method, "__");
        $method = explode("::", $method)[1] ?? $method;

        // Head parameters (depends on the method name) + default ones
        // Default parameters:
        // - "MAGIC ARGUMENTS"
        // - "MAGIC ARGUMENTS"+0: criteria
        // - "MAGIC ARGUMENTS"+1: orderBy
        // - "MAGIC ARGUMENTS"+2: groupBy
        // - "MAGIC ARGUMENTS"+3: limit
        // - "MAGIC ARGUMENTS"+4: offset

        // Definition of the returned values
        $magicFn = null;
        $magicArgs = [];

        // TODO: Safety check in dev mode only (maybe..)
        foreach($this->classMetadata->getFieldNames() as $field) {

            if (str_contains($field, self::OPTION_WITH_ROUTE )       ||
                str_contains($field, self::OPTION_BUT        )       ||
                str_contains($field, self::OPTION_MODEL      )       ||

                str_contains($field, self::OPTION_INSTANCEOF )       ||
                str_contains($field, self::OPTION_NOT_INSTANCEOF )   ||
                str_contains($field, self::OPTION_CLASSOF )       ||
                str_contains($field, self::OPTION_NOT_CLASSOF )   ||
                str_contains($field, self::OPTION_MEMBEROF )         ||
                str_contains($field, self::OPTION_NOT_MEMBEROF )     ||

                str_contains($field, self::OPTION_PARTIAL    )       ||
                str_contains($field, self::OPTION_INSENSITIVE)       ||
                str_contains($field, self::OPTION_CLOSESTTO)         ||
                str_contains($field, self::OPTION_FARESTTO)          ||
                str_contains($field, self::OPTION_STARTING_WITH)     ||
                str_contains($field, self::OPTION_ENDING_WITH)       ||
                str_contains($field, self::OPTION_NOT_STARTING_WITH) ||
                str_contains($field, self::OPTION_NOT_ENDING_WITH)   ||

                str_contains($field, self::OPTION_LOWER)             ||
                str_contains($field, self::OPTION_GREATER)           ||
                str_contains($field, self::OPTION_LOWER_EQUAL)       ||
                str_contains($field, self::OPTION_GREATER_EQUAL)     ||

                str_contains($field, self::OPTION_OVER)              ||
                str_contains($field, self::OPTION_NOT_OVER)          ||
                str_contains($field, self::OPTION_YOUNGER)           ||
                str_contains($field, self::OPTION_OLDER)             ||
                str_contains($field, self::OPTION_YOUNGER_EQUAL)     ||
                str_contains($field, self::OPTION_OLDER_EQUAL)       ||

                str_contains($field, self::OPTION_EQUAL)             ||
                str_contains($field, self::OPTION_NOT_EQUAL)         ||

                str_contains($field, self::SEPARATOR_AND      )      ||
                str_contains($field, self::SEPARATOR_OR       )      ||
                str_contains($field, self::SEPARATOR         ))

                throw new Exception(
                    "\"".$this->serviceEntity->getFqcnEntityName(). "\" entity has a field called \"$field\". ".
                    "This is unfortunate, because this word is used to customize DQL queries. ".
                    "Please build your own DQL query or change your database field name"
                );
        }

        // Find and sort the "With" list
        $withs = array_filter([
            self::OPTION_WITH_ROUTE => strpos($method, self::OPTION_WITH_ROUTE)
            /* ... more type of options here */
        ],fn ($value)  => ($value !== false));
        asort($withs);


        // Here are the resulting parameters
        $routeParameters = null;
        foreach(array_keys($withs) as $with) {

            switch($with) {

                // If variable with route parameter found.
                // Extract request from the provided arguments
                case self::OPTION_WITH_ROUTE:

                    $arrayOrEventOrRequest = array_shift($arguments);
                    if(!is_array($arrayOrEventOrRequest)) {

                        $request =
                            ($arrayOrEventOrRequest instanceof Request     ? $arrayOrEventOrRequest :
                            ($arrayOrEventOrRequest instanceof KernelEvent ? $arrayOrEventOrRequest->getRequest() : null));

                        if(!$request)
                            throw new Exception("At least one parameter requires route parameter in your method call. First parameter must be either an instance of 'Request', 'KernelEvent' or 'array'");

                        $arrayOrEventOrRequest = $request->attributes->get('_route_params');
                    }

                    $routeParameters = $arrayOrEventOrRequest;
                    break;

                default:
                    throw new Exception("Unexpected \"Entity\" proposition found: \"".$with."\"");
            }
        }

        // Extract method name and extra parameters
        $findRequest = self::REQUEST_CACHE."|".self::REQUEST_FIND;
        $countRequest = self::REQUEST_DISTINCT."|".self::REQUEST_COUNT;
        $lengthRequest = self::REQUEST_LENGTH;
        $specials = implode("|", self::getSpecials());

        $by = self::SEPARATOR_BY;
        $for = self::SEPARATOR_FOR;

        $special = null;
        $magicExtra = [];
        $magicFn = null;
        $byNames = [];

        if (preg_match('/^(?P<fn>(?:'.$findRequest.')(?P<special>'.$specials.')?'.$by.')(?P<names>.*)/', $method, $magicExtra)) {

            $byNames = array_pop_key("names", $magicExtra);
            $special = array_pop_key("special", $magicExtra);
            $special = $special ? only_alphachars(ucfirst($special)) : null;

            $magicFn = only_alphachars(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));

        } else if (preg_match('/^(?P<fn>(?:'.$findRequest.')(?P<special>'.$specials.')?)(?P<names>.*)/', $method, $magicExtra)) {

            $byNames = array_pop_key("names", $magicExtra);
            $special = array_pop_key("special", $magicExtra);
            $special = $special ? only_alphachars(ucfirst($special)) : null;

            $magicFn = only_alphachars(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));

        } else if (preg_match('/^(?P<fn>'.$countRequest.')(?:'.$for.'(?P<column>[^'.$by.']*))?'.$by.'(?P<names>.*)/', $method, $magicExtra)) {

            $byNames = array_pop_key("names", $magicExtra);
            $this->setColumn(lcfirst(array_pop_key("column", $magicExtra)) ?? null);

            $magicFn = only_alphanumerics(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));


        } else if (preg_match('/^(?P<fn>'.$countRequest.')(?:'.$for.'(?P<column>[^'.$by.']*))?(?P<names>.*)/', $method, $magicExtra)) {

            $byNames = array_pop_key("names", $magicExtra);
            $this->setColumn(lcfirst(array_pop_key("column", $magicExtra)) ?? null);

            $magicFn = only_alphanumerics(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));


        } else if (preg_match('/^(?P<fn>'.$lengthRequest.')(?P<column>[^'.$by.']+)'.$by.'(?P<names>.*)/', $method, $magicExtra)) {

            $byNames = array_pop_key("names", $magicExtra);
            $this->setColumn(lcfirst(array_pop_key("column", $magicExtra)) ?? null);

            $magicFn = only_alphanumerics(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));

        } else if (preg_match('/^(?P<fn>'.$lengthRequest.')(?P<column>[^'.$by.']+)(?P<names>.*)/', $method, $magicExtra)) {

            $byNames = array_pop_key("names", $magicExtra);

            $magicFn = only_alphanumerics(trim_brackets(array_pop_key("fn", $magicExtra)));
            $magicExtra = array_filter(array_key_removes_numerics($magicExtra));

            $this->setColumn(lcfirst(array_pop_key("column", $magicExtra)) ?? null);

        } else {

            throw new Exception(sprintf(
                'Undefined method "%s". The method name must start with ' .
                'either cache[PreviousOne|NextOne|One|Randomly|AtMost|Next|Previous|Last|LastOne|All][By], find[PreviousOne|NextOne|One|Randomly|AtMost|Next|Previous|Last|LastOne][By], distinctCount, count, lengthOf!',
                $method
            ));
        }

        // Handle special cases
        if(in_array($special, [self::getSpecial(self::SPECIAL_NEXTONE), self::getSpecial(self::SPECIAL_NEXT)])) {

            $id = $this->addCriteria("id", array_shift($arguments));
            $this->addCustomOption($id, self::OPTION_GREATER);

        } else if( in_array($special, [self::getSpecial(self::SPECIAL_PREVONE), self::getSpecial(self::SPECIAL_PREV)]) ) {

            $id = $this->addCriteria("id", array_shift($arguments));
            $this->addCustomOption($id, self::OPTION_LOWER);
        }

        // Reveal obvious logical ambiguities..
        $byNames = str_starts_with($byNames, self::SEPARATOR_BY) ? substr($byNames, strlen(self::SEPARATOR_BY)) : $byNames;
        if (str_contains($byNames, self::SEPARATOR_AND ) && str_contains($byNames, self::SEPARATOR_OR ))
            throw new Exception("\"".$byNames. "\" method gets an AND/OR ambiguity");

        // Desintangle logical operators
        if (str_contains($byNames, self::SEPARATOR_OR)) {

            $this->setSeparator(self::SEPARATOR_OR);
            $byNames = explode(self::SEPARATOR_OR, $byNames);

        } else {

            $this->setSeparator(self::SEPARATOR_AND);
            $byNames = explode(self::SEPARATOR_AND, $byNames);
        }

        $methodBak = $method;
        $method = str_starts_with($method, $magicFn) ? substr($method, strlen($magicFn)) : $method;

        $operator = self::OPTION_EQUAL; // Default case (e.g. "findBy" alone)
        foreach($byNames as $id => $by) {

            $oldBy = false;

            $isModel    = $isInsensitive = $isPartial = false;
            $withRoute  = false;
            $but        = false;

            $closestTo = $farestTo = false;
            $instanceOf = $notInstanceOf = false;
            $classOf = $notClassOf = false;
            $memberOf   = $notMemberOf   = false;

            $operator = self::OPTION_EQUAL;
            while ($oldBy != $by) {

                $oldBy = $by;

                $option = null;
                if ( str_starts_with($by, self::OPTION_PARTIAL) )
                    $option = self::OPTION_PARTIAL;
                else if ( str_starts_with($by, self::OPTION_INSENSITIVE) )
                    $option = self::OPTION_INSENSITIVE;
                else if ( str_ends_with($by, self::OPTION_WITH_ROUTE) )
                    $option = self::OPTION_WITH_ROUTE;
                else if ( str_ends_with($by, self::OPTION_CLOSESTTO) )
                    $option = self::OPTION_CLOSESTTO;
                else if ( str_ends_with($by, self::OPTION_FARESTTO) )
                    $option = self::OPTION_FARESTTO;
                else if ( str_ends_with($by, self::OPTION_BUT) )
                    $option = self::OPTION_BUT;
                else if ( str_ends_with($by, self::OPTION_INSTANCEOF) )
                    $option = self::OPTION_INSTANCEOF;
                else if ( str_ends_with($by, self::OPTION_NOT_INSTANCEOF) )
                    $option = self::OPTION_NOT_INSTANCEOF;
                else if ( str_ends_with($by, self::OPTION_CLASSOF) )
                    $option = self::OPTION_CLASSOF;
                else if ( str_ends_with($by, self::OPTION_NOT_CLASSOF) )
                    $option = self::OPTION_NOT_CLASSOF;
                else if ( str_ends_with($by, self::OPTION_MEMBEROF) )
                    $option = self::OPTION_MEMBEROF;
                else if ( str_ends_with($by, self::OPTION_NOT_MEMBEROF) )
                    $option = self::OPTION_NOT_MEMBEROF;

                else if ( str_ends_with($by, self::OPTION_STARTING_WITH) )
                    $option = self::OPTION_STARTING_WITH;
                else if ( str_ends_with($by, self::OPTION_ENDING_WITH) )
                    $option = self::OPTION_ENDING_WITH;
                else if ( str_ends_with($by, self::OPTION_NOT_STARTING_WITH) )
                    $option = self::OPTION_NOT_STARTING_WITH;
                else if ( str_ends_with($by, self::OPTION_NOT_ENDING_WITH) )
                    $option = self::OPTION_NOT_ENDING_WITH;

                else if ( str_ends_with($by, self::OPTION_YOUNGER) )
                    $option = self::OPTION_YOUNGER;
                else if ( str_ends_with($by, self::OPTION_YOUNGER_EQUAL) )
                    $option = self::OPTION_YOUNGER_EQUAL;
                else if ( str_ends_with($by, self::OPTION_OLDER) )
                    $option = self::OPTION_OLDER;
                else if ( str_ends_with($by, self::OPTION_OLDER_EQUAL) )
                    $option = self::OPTION_OLDER_EQUAL;

                else if ( str_ends_with($by, self::OPTION_LOWER_EQUAL) )
                    $option = self::OPTION_LOWER_EQUAL;
                else if ( str_ends_with($by, self::OPTION_LOWER) )
                    $option = self::OPTION_LOWER;

                else if ( str_ends_with($by, self::OPTION_GREATER_EQUAL) )
                    $option = self::OPTION_GREATER_EQUAL;
                else if ( str_ends_with($by, self::OPTION_GREATER) )
                    $option = self::OPTION_GREATER;

                else if ( str_ends_with($by, self::OPTION_NOT_EQUAL) )
                    $option = self::OPTION_NOT_EQUAL;
                else if ( str_ends_with($by, self::OPTION_EQUAL) )
                    $option = self::OPTION_EQUAL;

                switch($option) {

                    case self::OPTION_PARTIAL:
                        $isPartial = true;
                        list($method, $by) = $this->stripByFront($method, $by, $option);
                        break;

                    case self::OPTION_INSENSITIVE:
                        $isInsensitive = true;
                        list($method, $by) = $this->stripByFront($method, $by, $option);
                        break;

                    case self::OPTION_WITH_ROUTE:
                        $withRoute = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_BUT:
                        $but = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_CLOSESTTO:
                        $closestTo = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;
                    case self::OPTION_FARESTTO:
                        $farestTo = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_INSTANCEOF:
                        $instanceOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_NOT_INSTANCEOF:
                        $notInstanceOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_CLASSOF:
                        $classOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_NOT_CLASSOF:
                        $notClassOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_MEMBEROF:
                        $memberOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    case self::OPTION_NOT_MEMBEROF:
                        $notMemberOf = true;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;

                    // String related
                    case self::OPTION_STARTING_WITH:
                    case self::OPTION_ENDING_WITH:
                    case self::OPTION_NOT_STARTING_WITH:
                    case self::OPTION_NOT_ENDING_WITH:

                    // Datetime related
                    case self::OPTION_YOUNGER:
                    case self::OPTION_YOUNGER_EQUAL:
                    case self::OPTION_OLDER:
                    case self::OPTION_OLDER_EQUAL:
                    case self::OPTION_OVER:
                    case self::OPTION_NOT_OVER:

                    // Number related
                    case self::OPTION_LOWER:
                    case self::OPTION_LOWER_EQUAL:
                    case self::OPTION_GREATER:
                    case self::OPTION_GREATER_EQUAL:
                    case self::OPTION_EQUAL:
                    case self::OPTION_NOT_EQUAL:
                        $operator = $option;
                        list($method, $by) = $this->stripByEnd($method, $by, $option);
                        break;
                }
            }

            // First check if WithRoute special argument is found..
            // This argument will retrieve the value of the corresponding route parameter
            // and use it in the query
            if ($withRoute) {

                $key = array_shift($arguments);

                // Stop dev using partial information when using route parameter
                // Doing so.. user would be able to inject LIKE commands directly from URL..
                if($isPartial)
                    throw new Exception("Partial field \"$by\" using route parameter is not implemented. Consider removing \"Partial\" prefix from \"$methodBak\"");

                // Process self::OPTION_WITH_ROUTE method
                if ($method == self::OPTION_WITH_ROUTE)
                    throw new Exception("Missing parameter to associate with operator 'withRouteParameter'");

                $fieldValue = $routeParameters[$key] ?? null;
                if(!empty($fieldValue)) {

                    // Check if partial match is enabled
                    $by = lcfirst($by);

                    $id = $this->addCriteria($by, $fieldValue);
                    if ($operator == self::OPTION_EQUAL) $this->addCustomOption($id, $operator);
                    else throw new Exception("Unexpected operator \"".$operator."\" found in model definition");
                }

            } else if ($but) {

                $fieldValue = array_shift($arguments);
                if(!empty($fieldValue)) {

                    $by = "id";
                    $operator = self::OPTION_NOT_EQUAL;

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, $operator);
                }

            } else if ($instanceOf) {

                $fieldValue = array_shift($arguments);
                if(!empty($fieldValue)) {

                    $by = lcfirst(self::OPTION_INSTANCEOF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_INSTANCEOF);
                }

            } else if ($notInstanceOf) {

                $fieldValue = array_shift($arguments);
                if(!empty($fieldValue)) {

                    $by = lcfirst(self::OPTION_NOT_INSTANCEOF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_NOT_INSTANCEOF);
                }

            } else if ($classOf) {

                $fieldValue = array_shift($arguments);
                if(!empty($fieldValue)) {

                    $by = lcfirst(self::OPTION_CLASSOF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_CLASSOF);
                }

            } else if ($notClassOf) {

                $fieldValue = array_shift($arguments);
                if(!empty($fieldValue)) {

                    $by = lcfirst(self::OPTION_NOT_CLASSOF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_NOT_CLASSOF);
                }

            } else if ($memberOf) {

                $fieldValue = array_shift($arguments);
                if(!empty($fieldValue)) {

                    $by = lcfirst(self::OPTION_MEMBEROF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_MEMBEROF);
                }

            } else if ($notMemberOf) {

                $fieldValue = array_shift($arguments);
                if(!empty($fieldValue)) {

                    $by = lcfirst(self::OPTION_NOT_MEMBEROF);

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, self::OPTION_NOT_MEMBEROF);
                }

            } else if ($isModel) {

                list($method, $_) = $this->stripByEnd($method, $by, $option);
                $method = substr($method, 0, strpos($method, self::OPTION_MODEL));
                $by = lcfirst($by);

                $modelCriteria = [];
                $model = array_shift($arguments);

                if(is_object($model)) {

                    $reflClass = new ReflectionClass(get_class($model));
                    foreach ($reflClass->getProperties() as $field) {

                        $fieldName = $field->getName();
                        $field->setAccessible(true);

                        if (!$field->isInitialized($model)) continue;
                        if (!($fieldValue = $field->getValue($model)) ) continue;

                        if ($this->classMetadata->hasAssociation($fieldName)) {

                            $associationField = $this->classMetadata->getAssociationMapping($fieldName);
                            if (!array_key_exists("targetEntity", $associationField) || $field->getType() != $associationField["targetEntity"])
                                throw new Exception("Invalid association mapping \"$fieldName\" found (found \"".$field->getType()."\", expected type \"". $associationField["targetEntity"]."\") in \"" . $this->classMetadata->getName() . "\" entity, \"" . $reflClass->getName() . " cannot be applied\"");

                        } else if(!$this->classMetadata->hasField($fieldName))
                            throw new Exception("No field \"$fieldName\" (or association mapping) found in \"".$this->classMetadata->getName(). "\" entity, \"".$reflClass->getName()." cannot be applied\"");

                        if (( $fieldValue = $field->getValue($model) ))
                            $modelCriteria[$fieldName] = $fieldValue;
                    }

                } else if(is_array($model)) {

                    $modelCriteria = $this->entityHydrator->hydrate($this->classMetadata->getName(), $model);

                } else {

                    throw new Exception("Model expected to be an object or an array, currently \"". gettype($model)."\"");
                }

                if(!empty($modelCriteria)) {

                    $id = $this->addCriteria($by, $modelCriteria);
                    if ($isPartial) $this->addCustomOption($id, self::OPTION_PARTIAL);
                    if ($isInsensitive) $this->addCustomOption($id, self::OPTION_INSENSITIVE);

                    if ($operator == self::OPTION_EQUAL || $operator == self::OPTION_NOT_EQUAL) $this->addCustomOption($id, $operator);
                    else throw new Exception("Unexpected operator \"".$operator."\" found in model definition");
                }

            } else if($by) {

                $by = lcfirst($by);

                $fieldExpected = ($operator == self::OPTION_OVER || $operator == self::OPTION_NOT_OVER);
                $fieldValue = ($fieldExpected ? "CURDATE()" : array_shift($arguments));

                $id = $this->addCriteria($by, $fieldValue);
                if ($isPartial) $this->addCustomOption($id, self::OPTION_PARTIAL);
                if ($isInsensitive) $this->addCustomOption($id, self::OPTION_INSENSITIVE);

                if ($closestTo) $this->addCustomOption($id, self::OPTION_CLOSESTTO);
                if ($farestTo) $this->addCustomOption($id, self::OPTION_FARESTTO);

                if ($operator) $this->addCustomOption($id, $operator);
            }
        }

        // Index definition:
        // "criteria"  = argument #0, after removal of head parameters
        foreach($arguments as $i => $arg)
            $magicArgs[$i] = $arg; // +1 because of array shift

        //
        // Mark as cacheable (to be used in self::getQueryBuilder)
        if(str_starts_with($magicFn, self::REQUEST_CACHE)) {

            $magicFn = self::REQUEST_FIND.substr($magicFn, strlen(self::REQUEST_CACHE));
            $this->cacheable = true;
        }

        if(str_starts_with($magicFn, self::REQUEST_FIND.self::SPECIAL_ALL)) {

            if(str_ends_with($magicFn, self::SEPARATOR_BY))
                $magicFn = substr($magicFn, 0, -strlen(self::SEPARATOR_BY));

        } else if(!str_ends_with($magicFn, self::SEPARATOR_BY)) { // "Find" method without "By" must include a criteria to call __findBy

            $magicFn .= self::SEPARATOR_BY;
            array_unshift($magicArgs);
        }

        $magicFn = "__".$magicFn;

        $magicArgs[0] = $magicArgs[0] ?? [];
        $magicArgs[1] = array_merge($magicArgs[1] ?? [], $this->criteria ?? []); // Criteria
        $magicArgs[1] = array_merge($magicArgs[1], array_transforms(fn($k,$v) :array => ["special:".$k, $v], $magicExtra));

        return [$magicFn, $magicArgs];
    }


    protected function buildQueryExpr(QueryBuilder $qb, $field, $fieldValue)
    {
        $fieldID   = str_replace(".", "_", implode("_", $field));
        $fieldName = $this->getAlias($field[0]);
        $fieldRoot = implode(self::SEPARATOR, array_slice($field, count($field) - 2, 2));
        $fieldHead = explode(".", $fieldName)[0];

        $isPartial       = $this->findCustomOption($fieldRoot, self::OPTION_PARTIAL);
        $isInsensitive   = $this->findCustomOption($fieldRoot, self::OPTION_INSENSITIVE);
        $closestTo       = $this->findCustomOption($fieldRoot, self::OPTION_CLOSESTTO);
        $farestTo        = $this->findCustomOption($fieldRoot, self::OPTION_FARESTTO);

        $isMemberOf      = $this->findCustomOption($fieldRoot, self::OPTION_MEMBEROF);
        $isNotMemberOf   = $this->findCustomOption($fieldRoot, self::OPTION_NOT_MEMBEROF);
        $isInstanceOf    = $this->findCustomOption($fieldRoot, self::OPTION_INSTANCEOF);
        $isNotInstanceOf = $this->findCustomOption($fieldRoot, self::OPTION_NOT_INSTANCEOF);
        $isClassOf    = $this->findCustomOption($fieldRoot, self::OPTION_CLASSOF);
        $isNotClassOf = $this->findCustomOption($fieldRoot, self::OPTION_NOT_CLASSOF);

        $tableOperator   =

            ($this->findCustomOption ($fieldRoot, self::OPTION_INSTANCEOF)    ? self::OPTION_INSTANCEOF    :
            ($this->findCustomOption ($fieldRoot, self::OPTION_NOT_INSTANCEOF)? self::OPTION_NOT_INSTANCEOF:
            ($this->findCustomOption ($fieldRoot, self::OPTION_CLASSOF)       ? self::OPTION_CLASSOF       :
            ($this->findCustomOption ($fieldRoot, self::OPTION_NOT_CLASSOF)   ? self::OPTION_NOT_CLASSOF   :
            ($this->findCustomOption ($fieldRoot, self::OPTION_MEMBEROF)      ? self::OPTION_MEMBEROF      :
            ($this->findCustomOption ($fieldRoot, self::OPTION_NOT_MEMBEROF)  ? self::OPTION_NOT_MEMBEROF  :

            // Datetime related options
            ($this->findCustomOption ($fieldRoot, self::OPTION_OVER)          ? self::OPTION_OVER          :
            ($this->findCustomOption ($fieldRoot, self::OPTION_NOT_OVER)      ? self::OPTION_NOT_OVER      :
            ($this->findCustomOption ($fieldRoot, self::OPTION_OLDER)         ? self::OPTION_OLDER         :
            ($this->findCustomOption ($fieldRoot, self::OPTION_OLDER_EQUAL)   ? self::OPTION_OLDER_EQUAL   :
            ($this->findCustomOption ($fieldRoot, self::OPTION_YOUNGER)       ? self::OPTION_YOUNGER       :
            ($this->findCustomOption ($fieldRoot, self::OPTION_YOUNGER_EQUAL) ? self::OPTION_YOUNGER_EQUAL :

            // String related options
            ($this->findCustomOption ($fieldRoot, self::OPTION_STARTING_WITH)     ? self::OPTION_STARTING_WITH :
            ($this->findCustomOption ($fieldRoot, self::OPTION_ENDING_WITH)       ? self::OPTION_ENDING_WITH   :
            ($this->findCustomOption ($fieldRoot, self::OPTION_NOT_STARTING_WITH) ? self::OPTION_NOT_STARTING_WITH :
            ($this->findCustomOption ($fieldRoot, self::OPTION_NOT_ENDING_WITH)   ? self::OPTION_NOT_ENDING_WITH   :

            // Number related options
            ($this->findCustomOption ($fieldRoot, self::OPTION_GREATER)       ? self::OPTION_GREATER       :
            ($this->findCustomOption ($fieldRoot, self::OPTION_GREATER_EQUAL) ? self::OPTION_GREATER_EQUAL :
            ($this->findCustomOption ($fieldRoot, self::OPTION_LOWER)         ? self::OPTION_LOWER         :
            ($this->findCustomOption ($fieldRoot, self::OPTION_LOWER_EQUAL)   ? self::OPTION_LOWER_EQUAL   :
            ($this->findCustomOption ($fieldRoot, self::OPTION_NOT_EQUAL)     ? self::OPTION_NOT_EQUAL     : self::OPTION_EQUAL
        )))))))))))))))))))));

        if(is_array($tableOperator))
            throw new Exception("Too many operator requested for \"$fieldName\": ".implode(",", $tableOperator));

        switch($tableOperator) {

            case self::OPTION_MEMBEROF:
            case self::OPTION_NOT_MEMBEROF:
            case self::OPTION_INSTANCEOF:
            case self::OPTION_NOT_INSTANCEOF:
            case self::OPTION_CLASSOF:
            case self::OPTION_NOT_CLASSOF:
                $tableColumn = self::ALIAS_ENTITY;
                break;

            default:
                if($this->classMetadata->hasAssociation($fieldHead))
                    $tableColumn = self::ALIAS_ENTITY."_".$fieldName;
                else if($this->classMetadata->hasField($fieldHead))
                    $tableColumn = self::ALIAS_ENTITY.".".$fieldName;
                else $tableColumn = $fieldName;
        }

        $datetimeRequested = in_array($tableOperator, [self::OPTION_OVER, self::OPTION_NOT_OVER, self::OPTION_OLDER, self::OPTION_OLDER_EQUAL, self::OPTION_YOUNGER, self::OPTION_YOUNGER_EQUAL]);
        if($datetimeRequested) {

            if(is_numeric($fieldValue)) $fieldValue = ($fieldValue > 0 ? "+" : "-") . $fieldValue . " second";

                    if(in_array($tableOperator, [self::OPTION_OVER, self::OPTION_NOT_OVER])) $fieldValue = new \DateTime("now");
            else if($this->validateDate($fieldValue) || $fieldValue instanceof \DateTime) $fieldValue = new \DateTime($fieldValue);
            else if(in_array($tableOperator, [self::OPTION_YOUNGER, self::OPTION_YOUNGER_EQUAL])) {

                $subtract = strtr($fieldValue, ["+" => "-", "-" => "+"]);
                $fieldValue = (new \DateTime("now"))->modify($subtract);
            }
        }

        $regexRequested    = in_array($tableOperator, [self::OPTION_STARTING_WITH, self::OPTION_ENDING_WITH, self::OPTION_NOT_STARTING_WITH, self::OPTION_NOT_ENDING_WITH]);
        if($regexRequested) {

            $fieldValue = str_replace(["_", "\%"], ["\_", "\%"], $fieldValue);

                    if($tableOperator == self::OPTION_STARTING_WITH    ) $fieldValue = $fieldValue."%";
            else if($tableOperator == self::OPTION_ENDING_WITH      ) $fieldValue = "%".$fieldValue;
            else if($tableOperator == self::OPTION_NOT_STARTING_WITH) $fieldValue = $fieldValue."%";
            else if($tableOperator == self::OPTION_NOT_ENDING_WITH  ) $fieldValue = "%".$fieldValue;

            $fieldValue = ($isInsensitive ? mb_strtolower($fieldValue) : $fieldValue);
        }

        if($isInstanceOf || $isNotInstanceOf) {

            // Cast to array
            if (!is_array($fieldValue)) $fieldValue = $fieldValue !== null ? [$fieldValue] : [];

               $instanceOf = [];
            $notInstanceOf = [];
            foreach ($fieldValue as $value) {

                if($isInstanceOf) {

                    if( str_starts_with($value, "^") ) $notInstanceOf[] = $qb->expr()->not($qb->expr()->isInstanceOf($tableColumn, ltrim($value, "^")));
                    else $instanceOf[] = $qb->expr()->isInstanceOf($tableColumn, $value);

                } else {

                    if( str_starts_with($value, "^") ) $instanceOf[] = $qb->expr()->isInstanceOf($tableColumn, ltrim($value, "^"));
                    else $notInstanceOf[] = $qb->expr()->not($qb->expr()->isInstanceOf($tableColumn, $value));
                }
            }

            if($notInstanceOf) $instanceOf[] = $qb->expr()->andX(...$notInstanceOf);
            return $qb->expr()->orX(...$instanceOf);

        } else if($isClassOf || $isNotClassOf) {

            // Cast to array
            if (!is_array($fieldValue)) $fieldValue = $fieldValue !== null ? [$fieldValue] : [];

            $classOf = [];
            $notClassOf = [];
            foreach ($fieldValue as $value) {

                $className = ltrim($value, "^");
                $classMetadata = $this->entityManager->getClassMetadata($className);
                $classChildren = array_filter(array_values($classMetadata->discriminatorMap), fn($c) => $c != $className && is_instanceof($c, $className));

                if($isClassOf) {

                    if( !str_starts_with($value, "^") ) {

                        $subQb = [$qb->expr()->isInstanceOf($tableColumn, $value)];
                        foreach($classChildren as $child)
                            $subQb[] = $qb->expr()->not($qb->expr()->isInstanceOf($tableColumn, $child));

                        $classOf[] = $qb->expr()->andX(...$subQb);

                    } else {

                        $subQb = [$qb->expr()->not($qb->expr()->isInstanceOf($tableColumn, $value))];
                        foreach($classChildren as $child)
                            $subQb[] = $qb->expr()->isInstanceOf($tableColumn, $child);

                        $classOf[] = $qb->expr()->orX(...$subQb);
                    }

                } else {

                    if( str_starts_with($value, "^") ) {

                        $subQb = [$qb->expr()->not($qb->expr()->isInstanceOf($tableColumn, $value))];
                        foreach($classChildren as $child)
                            $subQb[] = $qb->expr()->isInstanceOf($tableColumn, $child);

                        $classOf[] = $qb->expr()->orX(...$subQb);

                    } else {

                        $subQb = [$qb->expr()->isInstanceOf($tableColumn, $value)];
                        foreach($classChildren as $child)
                            $subQb[] = $qb->expr()->not($qb->expr()->isInstanceOf($tableColumn, $child));

                        $classOf[] = $qb->expr()->andX(...$subQb);
                    }
                }
            }

            if($notClassOf) $classOf[] = $qb->expr()->andX(...$notClassOf);
            return $qb->expr()->orX(...$classOf);

        } else if($isMemberOf || $isNotMemberOf) {

            // Cast to array
            if (!is_array($fieldValue)) $fieldValue = $fieldValue !== null ? [$fieldValue] : [];

               $memberOf = [];
            $notMemberOf = [];
            foreach ($fieldValue as $value) {

                if($isMemberOf) $memberOf[] = $qb->expr()->isMemberOf($tableColumn, $value);
                else $notMemberOf[] = $qb->expr()->isMemberOf($tableColumn, $value);
            }

            if($notMemberOf) $memberOf[] = $qb->expr()->andX(...$notMemberOf);
            return $qb->expr()->orX(...$memberOf);

        } else {

            if ($this->classMetadata->hasAssociation($fieldHead)) {

                if($this->classMetadata->isAssociationInverseSide($fieldHead))
                    throw new Exception("Association \"$fieldHead\" for \"".$this->classMetadata->getName()."\" is not owning side");

                $fieldID = self::ALIAS_ENTITY."_".$fieldID;

                if($isPartial) { // PARTIAL HAS TO BE CHECKED SINCE THE UPDATE.. NOT TESTED

                    // Cast to array
                    $qb = $this->innerJoin($qb, $fieldHead);
                    if (!is_array($fieldValue)) $fieldValue = ($fieldValue !== null)? [$fieldValue] : [];
                    foreach ($fieldValue as $subFieldID => $subFieldValue)
                        $qb->setParameter($fieldID."_".$subFieldID, $subFieldValue);

                } else {

                    if(!is_array($fieldValue)) {

                        $qb = $this->innerJoin($qb, $fieldHead);
                        $qb->setParameter($fieldID, $fieldValue);

                    } else {

                        $fieldValue = array_filter($fieldValue);
                        if($fieldValue) {

                            $qb = $this->innerJoin($qb, $fieldHead);
                            $qb->setParameter($fieldID, $fieldValue);
                        }
                    }
                }

            } else if(!is_array($fieldValue)) {

                $qb = $this->innerJoin($qb, $fieldHead);
                $qb->setParameter($fieldID, $fieldValue);

            } else {

                $fieldValue = array_filter($fieldValue);
                if($fieldValue) {
                    $qb = $this->innerJoin($qb, $fieldHead);
                    $qb->setParameter($fieldID, $fieldValue);
                }
            }

            if ($isInsensitive) $tableColumn = "LOWER(" . $tableColumn . ")";
            if ($isPartial) { // PARTIAL HAS TO BE CHECKED SINCE THE UPDATE.. NOT TESTED

                if($tableOperator != self::OPTION_EQUAL && $tableOperator != self::OPTION_NOT_EQUAL)
                    throw new Exception("Invalid operator for association field \"$fieldName\": ".$tableOperator);

                $expr = [];
                foreach ($fieldValue as $subFieldID => $_) {

                    $fnExpr = ($tableOperator == self::OPTION_EQUAL ? "like" : "notLike");
                    $expr[] = $qb->expr()->$fnExpr($fieldID, ":${fieldID}_${subFieldID}");
                }

                $fnExpr = ($tableOperator == self::OPTION_EQUAL ? "orX" : "andX");
                return $qb->expr()->$fnExpr(...$expr);

            } else if (is_array($fieldValue)) {

                    if($tableOperator == self::OPTION_EQUAL)     $tableOperator = "IN";
                else if($tableOperator == self::OPTION_NOT_EQUAL) $tableOperator = "NOT IN";
                else throw new Exception("Invalid operator for field \"$fieldName\": ".$tableOperator);

                return !empty($fieldValue) ? "${tableColumn} ${tableOperator} (:${fieldID})" : null;

            } else if($regexRequested) {

                     if($tableOperator == self::OPTION_STARTING_WITH) $tableOperator = "like";
                else if($tableOperator == self::OPTION_ENDING_WITH)   $tableOperator = "like";
                else if($tableOperator == self::OPTION_NOT_STARTING_WITH)  $tableOperator = "notLike";
                else if($tableOperator == self::OPTION_NOT_ENDING_WITH)    $tableOperator = "notLike";

                return $qb->expr()->$tableOperator($tableColumn, ":$fieldID");

            } else if($closestTo || $farestTo) {

                return "ABS(".$tableColumn." - :".$fieldID.")";

            } else {

                     if($tableOperator == self::OPTION_EQUAL)         $tableOperator = "=";
                else if($tableOperator == self::OPTION_NOT_EQUAL)     $tableOperator = "!=";
                else if($tableOperator == self::OPTION_GREATER)       $tableOperator = ">";
                else if($tableOperator == self::OPTION_GREATER_EQUAL) $tableOperator = ">=";
                else if($tableOperator == self::OPTION_LOWER)         $tableOperator = "<";
                else if($tableOperator == self::OPTION_LOWER_EQUAL)   $tableOperator = "<=";
                else if($tableOperator == self::OPTION_YOUNGER)       $tableOperator = ">";
                else if($tableOperator == self::OPTION_YOUNGER_EQUAL) $tableOperator = ">=";
                else if($tableOperator == self::OPTION_OLDER)         $tableOperator = "<";
                else if($tableOperator == self::OPTION_OLDER_EQUAL)   $tableOperator = "<=";
                else if($tableOperator == self::OPTION_OVER)          $tableOperator = "<=";
                else if($tableOperator == self::OPTION_NOT_OVER)      $tableOperator = ">";
                else throw new Exception("Invalid operator for field \"$fieldName\": ".$tableOperator);

                return "${tableColumn} ${tableOperator} :{$fieldID}";
            }
        }

        throw new Exception("Failed to build expression \"".$field."\": ".$fieldValue);
    }

    protected function getQueryBuilder(array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null): ?QueryBuilder
    {
        /**
         * @QueryBuilder
         */
        $qb = $this->serviceEntity
            ->createQueryBuilder(self::ALIAS_ENTITY)
            ->setMaxResults($limit ?? null)
            ->setFirstResult($offset ?? null)
            ->setCacheable($this->cacheable);

        $this->innerJoinList[spl_object_hash($qb)] = [];

        // Prepare criteria variable
        foreach ($criteria as $field => $fieldValue) {

            $field     = explode(self::SEPARATOR, $field);
            $fieldName = $field[0];

            if($fieldValue instanceof PersistentCollection)
                 throw new Exception("You passed a PersistentCollection for field \"".$fieldName."\"");

            // Handle partial entity/model input criteria
            if($fieldName == lcfirst(self::OPTION_MODEL)) {

                $expr = [];
                foreach ($fieldValue ?? [] as $entryID => $entryValue) {

                    $newField = [];
                    foreach ($field as $key => $value)
                        $newField[$key] = $value;

                    array_unshift($newField, $entryID);

                    $queryExpr = $this->buildQueryExpr($qb, $newField, $entryValue);
                    if($queryExpr == null) continue;

                    // In case of association field, compare value directly
                    if ($this->classMetadata->hasAssociation($entryID)) $qb->andWhere($queryExpr);
                    // If standard field, check for partial information
                    else $expr[] = $queryExpr;
                }

                $expr = empty($expr) ? null : $qb->expr()->orX(...$expr);

            } else {

                // Default query builder
                $expr = $this->buildQueryExpr($qb, $field, $fieldValue);
                if($expr == null) continue;

                // Custom process in case of closest/farest
                $fieldRoot = implode(self::SEPARATOR, array_slice($field, count($field) - 2, 2));
                $closestTo       = $this->findCustomOption($fieldRoot, self::OPTION_CLOSESTTO);
                $farestTo        = $this->findCustomOption($fieldRoot, self::OPTION_FARESTTO);
                if ($closestTo || $farestTo) {
                    $orderBy[$expr] = $closestTo ? "ASC" : "DESC";
                    $expr = null;
                }
            }

            if($expr !== null) {

                // Apply logical operator (if needed)
                $separator = $this->getSeparator();
                switch ($separator) {

                    case self::SEPARATOR_OR: $qb->orWhere($expr);
                        break;

                    case self::SEPARATOR_AND: $qb->andWhere($expr);
                        break;

                    default:
                        throw new Exception("Unknown separator \"".$separator."\" provided");
                }
            }
        }

        // Ordering result by group
        if($groupBy) $qb->select(self::ALIAS_ENTITY . " AS entity");
        else $qb->select(self::ALIAS_ENTITY);

        $qb = $this->selectAs($qb, $selectAs);
        $qb = $this->orderBy ($qb, $orderBy);
        $qb = $this->groupBy ($qb, $groupBy);

        return $qb;
    }

    protected function getQuery(array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null): ?Query
    {
        $qb = $this->getQueryBuilder($selectAs, $criteria, $orderBy, $groupBy, $limit, $offset);
        $query = $qb->getQuery();

        if($query->isCacheable()) {

            $entityName  = $this->classMetadata->getName();

            if(class_implements_interface($entityName, TranslatableInterface::class)) {

                $qb->leftJoin(self::ALIAS_ENTITY.".translations", self::ALIAS_TRANSLATIONS);
                $qb->addSelect(self::ALIAS_TRANSLATIONS);
            }

            $query = $qb->getQuery();
            $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TranslatableWalker::class);
        }

        return $query;
    }

    protected function getQueryWithCount(array $selectAs = [], array $criteria = [], ?string $mode = "", ?array $orderBy = null, $groupBy = null)
    {
        if($mode == self::COUNT_ALL) $mode = "";
        if($mode && $mode != self::COUNT_DISTINCT)
            throw new Exception("Unexpected \"mode\" provided: \"". $mode."\"");

        $column = $this->getAlias($this->getColumn());
        if($this->classMetadata->hasAssociation($column))
            $column = self::ALIAS_ENTITY."_".$column;
        else if($this->classMetadata->hasField($column))
            $column = self::ALIAS_ENTITY.".".$column;
        else $column = self::ALIAS_ENTITY.".id";

        $qb = $this->getQueryBuilder($selectAs, $criteria, $orderBy, $groupBy);
        $this->innerJoin($qb, $column);

        $qb->select('COUNT('.trim($mode.' '.$column).') AS count');

        return $qb->getQuery();
    }

    protected function getQueryWithLength(array $selectAs = [], array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null)
    {
        $column = $this->getAlias($this->getColumn());
        if($this->classMetadata->hasAssociation($column))
            $column = self::ALIAS_ENTITY."_".$column;
        else if($this->classMetadata->hasField($column))
            $column = self::ALIAS_ENTITY.".".$column;

        $qb = $this->getQueryBuilder($selectAs, $criteria, $orderBy, $groupBy, $limit, $offset);
        $this->innerJoin($qb, $column);

        $qb->select("LENGTH(".self::ALIAS_ENTITY.".".$column.") as length");

        return $qb->getQuery();
    }

    protected function selectAs(QueryBuilder $qb, $selectAs)
    {
        if(!$selectAs) return $qb;

        foreach($selectAs as $select => $as)
            $qb->addSelect(is_string($select) ? $as ." AS ". $select : $as);

        return $qb;
    }

    protected function groupBy(QueryBuilder $qb, $groupBy)
    {
        if(!$groupBy) return $qb;

        $column = explode("\\", $this->classMetadata->getName());
        $column = lcfirst(end($column));

        if(is_string($groupBy)) $groupBy = [$groupBy];
        if(!is_array($groupBy)) throw new Exception("Unexpected \"groupBy\" argument type provided \"".gettype($groupBy)."\"");

        foreach ($groupBy as $name => $column) {

            $alias = str_replace(".", "_", $column);

            $column = implode(".", array_map(fn ($c) => $this->getAlias($c), explode(".", $c)));
            $columnHead = explode(".", $column)[0] ?? $column;

            if($this->classMetadata->hasAssociation($columnHead))
                 $groupBy[$name] = self::ALIAS_ENTITY."_".$column;
            else if($this->classMetadata->hasField($columnHead))
                 $groupBy[$name] = self::ALIAS_ENTITY.".".$column;

            if($groupBy[$name] == self::ALIAS_ENTITY.".".$alias) $qb->addSelect($groupBy[$name]);
            else $qb->addSelect("(".$groupBy[$name].") AS ".$alias);

            $qb = $this->innerJoin($qb, $alias);
        }

        return $qb->groupBy(implode(",", $groupBy));
    }

    protected $innerJoinList = [];
    protected function innerJoin(QueryBuilder $qb, $innerJoin)
    {
        if(in_array($innerJoin, $this->innerJoinList[spl_object_hash($qb)])) return $qb;
        if ($this->classMetadata->hasAssociation($innerJoin)) {

            $qb->innerJoin(self::ALIAS_ENTITY.".".$innerJoin, self::ALIAS_ENTITY."_".$innerJoin);
            $this->innerJoinList[spl_object_hash($qb)][] = $innerJoin;
        }

        return $qb;
    }

    protected function orderBy(QueryBuilder $qb, string|array|null $orderBy)
    {
        if(!$orderBy) return $qb;

        $column = explode("\\", $this->classMetadata->getName());
        $column = lcfirst(end($column));

        if(is_string($orderBy)) $orderBy = [$orderBy => "ASC"];
        if(!is_array($orderBy)) throw new Exception("Unexpected \"orderBy\" argument type provided \"".gettype($orderBy)."\"");

        $first = true;
        foreach ($orderBy as $name => $value) {

            $path = array_map(fn ($name) => $this->getAlias($name), explode(".", $name));
            $name = implode(".", $path);
            $entity = $path[0];

            $isRandom = ($name == "id" && strtolower($value) == "rand");
            if(!$isRandom) {

                if($this->classMetadata->hasField($entity))
                    $formattedName = self::ALIAS_ENTITY.".".$name;
                else if($this->classMetadata->hasAssociation($entity))
                    $formattedName = self::ALIAS_ENTITY."_".$name;
                else
                    $formattedName = $name;

                $qb = $this->innerJoin($qb, $entity);
            }

            $orderBy   = $first ? "orderBy" : "addOrderBy";

            if($isRandom) $qb->orderBy('RAND()');
            else if(is_array($value))
                $qb->add($orderBy, "FIELD(".$formattedName.",".implode(",",$value).")");
            else
                $qb->$orderBy($formattedName, $value);

            $first = false;
        }

        return $qb;
    }
}
