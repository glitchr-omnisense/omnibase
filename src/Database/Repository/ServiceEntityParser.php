<?php

namespace Base\Database\Repository;

use Base\Database\TranslatableInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
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

    public const REQUEST_FIND      = "find";
    public const REQUEST_CACHE     = "cache";
    public const REQUEST_COUNT     = "count";
    public const REQUEST_DISTINCT  = "distinctCount";
    public const REQUEST_LENGTH    = "lengthOf";

    public const SPECIAL_ALL    = "All";
    public const SPECIAL_ONE    = "One";
    public const SPECIAL_ATMOST = "AtMost";
    public const SPECIAL_LAST   = "Last";
    public const SPECIAL_RAND   = "Randomly";
    protected static function getSpecials(): array 
    { 
        return [self::SPECIAL_ALL, self::SPECIAL_ONE, self::SPECIAL_ATMOST, self::SPECIAL_LAST, self::SPECIAL_RAND]; 
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
    public const OPTION_INSENSITIVE   = "Insensitive";
    public const OPTION_WITH_ROUTE    = "WithRoute";
    public const OPTION_PARTIAL       = "Partial";
    public const OPTION_MODEL         = "Model";
    public const OPTION_INSTANCEOF    = "InstanceOf";
    public const OPTION_BUT           = "But";
    
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
    public function getClassMetadata(): ClassMetadata { return $this->classMetadata; }

    public function __construct(ServiceEntityRepository $serviceEntity, ClassMetadata $classMetadata) 
    {
        $this->serviceEntity = $serviceEntity;
        $this->classMetadata = $classMetadata;

        $this->criteria = [];
        $this->options = [];
        $this->operator = null;

        foreach(self::getSpecials() as $special) {

            if($special == self::SPECIAL_ALL) continue;
            $specialMethod = "__".self::REQUEST_FIND.$special.self::SEPARATOR_BY;

            if(!method_exists($this, $specialMethod))
                throw new Exception("Special \"$special\" option is missing its find method : ".$specialMethod);
        }
    }

    protected function __findBy         (array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null): ?Query { return $this->getQuery($criteria, $orderBy, $groupBy, $limit, $offset);   }
    protected function __findRandomlyBy (array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null): ?Query { return $this->__findBy($criteria, array_merge(["id" => "rand"], $orderBy ?? []), $groupBy, $limit, $offset); }
    protected function __findAll        (                      $orderBy = null, $groupBy = null                               ): ?Query { return $this->__findBy(       [], $orderBy, $groupBy, null, null);   }
    protected function __findOneBy      (array $criteria = [], $orderBy = null, $groupBy = null                               ) { return $this->__findBy   ($criteria, $orderBy, $groupBy, 1, null)->getOneOrNullResult(); }
    protected function __findLastBy     (array $criteria = [], $orderBy = null, $groupBy = null                               ) { return $this->__findOneBy($criteria, array_merge($orderBy ?? [], ['id' => 'DESC']), $groupBy, 1, null) ?? null; }
    protected function __findAtMostBy   (array $criteria = [], $orderBy = null, $groupBy = null                               ): ?Query
    { 
        $limit = array_unshift($criteria);
        return $this->__findBy($criteria, $orderBy, $groupBy, $limit, null); 
    }

    protected function __lengthOf     (array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null) { return $this->getQueryWithLength($criteria, $orderBy, $groupBy, $limit, $offset)->getResult(); }
    protected function __distinctCount(array $criteria, $groupBy = null): int { return $this->__count($criteria, self::COUNT_DISTINCT, $groupBy); }
    protected function __count        (array $criteria, ?string $mode = self::COUNT_ALL, ?array $orderBy = null, $groupBy = null) 
    {
        $query = $this->getQueryWithCount($criteria, $mode, $orderBy, $groupBy); 
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
        $this->criteria = [];
        $this->options = [];
        $this->operator = null;
        $this->cacheable = false;

        return $ret;
    }

    protected function getAlias($alias) { 

        $this->getClassMetadata()->fieldNames[$alias] ?? $alias;
        return $this->getClassMetadata()->fieldNames[$alias] ?? $alias;
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

    public function  getCustomOption(string $id) { return $this->options[$id] ?? []; }
    public function findCustomOption(string $id, string $option) { return in_array($option, $this->getCustomOption($id)); }
    protected function addCustomOption(string $id, $option)
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
        foreach($this->getClassMetadata()->getFieldNames() as $field) {

            if (str_contains($field, self::OPTION_WITH_ROUTE )   ||
                str_contains($field, self::OPTION_BUT        )   ||
                str_contains($field, self::OPTION_MODEL      )   ||
                str_contains($field, self::OPTION_INSTANCEOF )   ||
                str_contains($field, self::OPTION_PARTIAL    )   ||
                str_contains($field, self::OPTION_INSENSITIVE)   ||
                str_contains($field, self::OPTION_STARTING_WITH) ||
                str_contains($field, self::OPTION_ENDING_WITH)   ||
                str_contains($field, self::OPTION_NOT_STARTING_WITH) ||
                str_contains($field, self::OPTION_NOT_ENDING_WITH)   ||

                str_contains($field, self::OPTION_LOWER)         ||
                str_contains($field, self::OPTION_GREATER)       ||
                str_contains($field, self::OPTION_LOWER_EQUAL)   ||
                str_contains($field, self::OPTION_GREATER_EQUAL) ||

                str_contains($field, self::OPTION_OVER)          ||
                str_contains($field, self::OPTION_NOT_OVER)      ||
                str_contains($field, self::OPTION_YOUNGER)       ||
                str_contains($field, self::OPTION_OLDER)         ||
                str_contains($field, self::OPTION_YOUNGER_EQUAL) ||
                str_contains($field, self::OPTION_OLDER_EQUAL)   ||

                str_contains($field, self::OPTION_EQUAL)         ||
                str_contains($field, self::OPTION_NOT_EQUAL)     ||

                str_contains($field, self::SEPARATOR_AND      )  ||
                str_contains($field, self::SEPARATOR_OR       )  ||
                str_contains($field, self::SEPARATOR         ))

                throw new Exception(
                    "\"".$this->serviceEntity->getEntityName(). "\" entity has a field called \"$field\". ".
                    "This is unfortunate, because this word is used to customize DQL queries. ".
                    "Please build your own DQL query or change your database field name"
                );
        }

        // Find and sort the "With" list
        $withs = array_filter([
            self::OPTION_WITH_ROUTE => strpos($method, self::OPTION_WITH_ROUTE)
            /* ... */
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

        if (preg_match('/^((?:'.$findRequest.')(?:'.$specials.')?'.$by.')(.*)/', $method, $matches)) {

            $magicFn = $matches[1] ?? "";
            $byNames = $matches[2];

        } else if (preg_match('/^((?:'.$findRequest.')(?:'.$specials.')?)(.*)/', $method, $matches)) {

            $magicFn = $matches[1] ?? "";
            $byNames = $matches[2];

        } else if (preg_match('/^('.$countRequest.')(?:'.$for.'([^'.$by.']*))?'.$by.'(.*)/', $method, $matches)) {
            
            $magicFn = $matches[1] ?? "";
            $byNames = $matches[3] ?? "";

            $this->setColumn(lcfirst($matches[2]) ?? null);

        } else if (preg_match('/^('.$countRequest.')(?:'.$for.'([^'.$by.']*))?(.*)/', $method, $matches)) {
            
            $magicFn = $matches[1] ?? "";
            $byNames = $matches[3] ?? "";

            $this->setColumn(lcfirst($matches[2]) ?? null);

        } else if (preg_match('/^('.$lengthRequest.')([^'.$by.']+)'.$by.'(.*)/', $method, $matches)) {
            
            $magicFn = $matches[1] ?? "";
            $byNames = $matches[3] ?? "";

            $this->setColumn(lcfirst($matches[2]) ?? null);

        } else if (preg_match('/^('.$lengthRequest.')([^'.$by.']+)(.*)/', $method, $matches)) {
            
            $magicFn = $matches[1] ?? "";
            $byNames = $matches[3] ?? "";

            $this->setColumn(lcfirst($matches[2]) ?? null);

        } else {

            throw new Exception(sprintf(
                'Undefined method "%s". The method name must start with ' .
                'either cache[One|Randomly|AtMost|Last|All][By], find[One|Randomly|AtMost|Last][By], distinctCount, count, lengthOf!',
                $method
            ));
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

            $oldBy = null;

            $isModel = $isInsensitive = $isPartial = false;
            $withRoute = null;
            $but = null;
            $instanceOf = null;

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
                else if ( str_ends_with($by, self::OPTION_BUT) )
                    $option = self::OPTION_BUT;
                else if ( str_ends_with($by, self::OPTION_INSTANCEOF) )
                    $option = self::OPTION_INSTANCEOF;

                else if ( str_ends_with($by, self::OPTION_STARTING_WITH) )
                    $option = self::OPTION_STARTING_WITH;
                else if ( str_ends_with($by, self::OPTION_ENDING_WITH) )
                    $option = self::OPTION_ENDING_WITH;
                else if ( str_ends_with($by, self::OPTION_NOT_STARTING_WITH) )
                    $option = self::OPTION_NOT_STARTING_WITH;
                else if ( str_ends_with($by, self::OPTION_NOT_ENDING_WITH) )
                    $option = self::OPTION_NOT_ENDING_WITH;
                
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

                else if ( str_ends_with($by, self::OPTION_YOUNGER) )
                    $option = self::OPTION_YOUNGER;
                else if ( str_ends_with($by, self::OPTION_YOUNGER_EQUAL) )
                    $option = self::OPTION_YOUNGER_EQUAL;
                else if ( str_ends_with($by, self::OPTION_OLDER) )
                    $option = self::OPTION_OLDER;
                else if ( str_ends_with($by, self::OPTION_OLDER_EQUAL) )
                    $option = self::OPTION_OLDER_EQUAL;

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

                    case self::OPTION_INSTANCEOF:
                        $instanceOf = true;
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
                    $operator = self::OPTION_INSTANCEOF;

                    $id = $this->addCriteria($by, $fieldValue);
                    $this->addCustomOption($id, $operator);
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

                        if ($this->getClassMetadata()->hasAssociation($fieldName)) {

                            $associationField = $this->getClassMetadata()->getAssociationMapping($fieldName);
                            if (!array_key_exists("targetEntity", $associationField) || $field->getType() != $associationField["targetEntity"])
                                throw new Exception("Invalid association mapping \"$fieldName\" found (found \"".$field->getType()."\", expected type \"". $associationField["targetEntity"]."\") in \"" . $this->getClassMetadata()->getName() . "\" entity, \"" . $reflClass->getName() . " cannot be applied\"");

                        } else if(!$this->getClassMetadata()->hasField($fieldName))
                            throw new Exception("No field \"$fieldName\" (or association mapping) found in \"".$this->getClassMetadata()->getName(). "\" entity, \"".$reflClass->getName()." cannot be applied\"");

                        if (( $fieldValue = $field->getValue($model) ))
                            $modelCriteria[$fieldName] = $fieldValue;
                    }

                } else if(is_array($model)) {

                    $modelCriteria = $model;

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
                if ($operator) $this->addCustomOption($id, $operator);
            }
        }

        // Index definition:
        // "criteria"  = argument #0, after removal of head parameters
        foreach($arguments as $i => $arg) 
            $magicArgs[$i] = $arg; // +1 because of array shift

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
        $magicArgs[0] = array_merge($magicArgs[0] ?? [], $this->criteria ?? []);
        return [$magicFn, $magicArgs];
    }

    protected function buildQueryExpr(QueryBuilder $qb, $field, $fieldValue)
    {
        $fieldName = $this->getAlias($field[0]);
        $fieldID   = implode("_", $field);
        $fieldRoot = implode(self::SEPARATOR, array_slice($field, count($field) - 2, 2));

        $isInstanceOf  = $this->findCustomOption($fieldRoot, self::OPTION_INSTANCEOF);
        $isPartial     = $this->findCustomOption($fieldRoot, self::OPTION_PARTIAL);
        $isInsensitive = $this->findCustomOption($fieldRoot, self::OPTION_INSENSITIVE);
        $tableOperator =

            ($this->findCustomOption ($fieldRoot, self::OPTION_INSTANCEOF)    ? self::OPTION_INSTANCEOF    :

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
        ))))))))))))))));

        if(is_array($tableOperator))
            throw new Exception("Too many operator requested for \"$fieldName\": ".implode(",", $tableOperator));

        if($tableOperator == self::OPTION_INSTANCEOF) $tableColumn = self::ALIAS_ENTITY;
        else {
            
            $tableColumn = self::ALIAS_ENTITY . ($this->getClassMetadata()->hasAssociation($fieldName) ? "_" : ".") . $fieldName;

            // Regular field: string, datetime..
            if ($this->getClassMetadata()->hasAssociation($fieldName))
                $qb = $this->innerJoin($qb, $fieldName);
        }

        $regexRequested    = in_array($tableOperator, [self::OPTION_STARTING_WITH, self::OPTION_ENDING_WITH, self::OPTION_NOT_STARTING_WITH, self::OPTION_NOT_ENDING_WITH]);
        $datetimeRequested = in_array($tableOperator, [self::OPTION_OVER, self::OPTION_NOT_OVER, self::OPTION_OLDER, self::OPTION_OLDER_EQUAL, self::OPTION_YOUNGER, self::OPTION_YOUNGER_EQUAL]);

        if(is_string($fieldValue)) {

            if($datetimeRequested) {

                if(in_array($tableOperator, [self::OPTION_OVER, self::OPTION_NOT_OVER])) $fieldValue = new \DateTime("now");
                else if($this->validateDate($fieldValue) || $fieldValue instanceof \DateTime) $fieldValue = new \DateTime($fieldValue);
                else if(in_array($tableOperator, [self::OPTION_YOUNGER, self::OPTION_YOUNGER_EQUAL])) {

                    $subtract = strtr($fieldValue, ["+" => "-", "-" => "+"]);
                    $fieldValue = (new \DateTime("now"))->modify($subtract);
                }
            }

            if($regexRequested) {

                $fieldValue = str_replace(["_", "\%"], ["\_", "\%"], $fieldValue);

                    if($tableOperator == self::OPTION_STARTING_WITH    ) $fieldValue = $fieldValue."%";
                else if($tableOperator == self::OPTION_ENDING_WITH      ) $fieldValue = "%".$fieldValue;
                else if($tableOperator == self::OPTION_NOT_STARTING_WITH) $fieldValue = $fieldValue."%";
                else if($tableOperator == self::OPTION_NOT_ENDING_WITH  ) $fieldValue = "%".$fieldValue;

                $fieldValue = ($isInsensitive ? mb_strtolower($fieldValue) : $fieldValue);
            }
        }

        if($isInstanceOf) {
            
            // Cast to array
            if (!is_array($fieldValue)) $fieldValue = $fieldValue !== null ? [$fieldValue] : [];

               $instanceOf = [];
            $notInstanceOf = [];
            foreach ($fieldValue as $value) {

                if( str_starts_with($value, "^") )
                    $notInstanceOf[] = $qb->expr()->not($qb->expr()->isInstanceOf($tableColumn, ltrim($value, "^")));
                else 
                    $instanceOf[]    = $qb->expr()->isInstanceOf($tableColumn, $value);
            }

            if($notInstanceOf) $instanceOf[] = $qb->expr()->andX(...$notInstanceOf);
            return $qb->expr()->orX(...$instanceOf);

        } else {

            $qb->setParameter($fieldID, $fieldValue);

            if ($isInsensitive) $tableColumn = "LOWER(" . $tableColumn . ")";
            if ($isPartial) {

                if($tableOperator != self::OPTION_EQUAL && $tableOperator != self::OPTION_NOT_EQUAL)
                    throw new Exception("Invalid operator for association field \"$fieldName\": ".$tableOperator);

                // Cast to array
                if (!is_array($fieldValue)) $fieldValue = $fieldValue !== null ? [$fieldValue] : [];

                $expr = [];
                foreach ($fieldValue as $_ => $_) {

                    $fnExpr = ($tableOperator == self::OPTION_EQUAL ? "like" : "notLike");
                    $expr[] = $qb->expr()->$fnExpr($tableColumn, ":$fieldID");
                }

                $fnExpr = ($tableOperator == self::OPTION_EQUAL ? "orX" : "andX");
                return $qb->expr()->$fnExpr(...$expr);

            } else if (is_array($fieldValue)) {

                    if($tableOperator == self::OPTION_EQUAL)     $tableOperator = "IN";
                else if($tableOperator == self::OPTION_NOT_EQUAL) $tableOperator = "NOT IN";
                else throw new Exception("Invalid operator for field \"$fieldName\": ".$tableOperator);

                return "${tableColumn} ${tableOperator} (:${fieldID})";
                
            } else if($regexRequested) {
            
                    if($tableOperator == self::OPTION_STARTING_WITH) $tableOperator = "like";
                else if($tableOperator == self::OPTION_ENDING_WITH)   $tableOperator = "like";
                else if($tableOperator == self::OPTION_NOT_STARTING_WITH)  $tableOperator = "notLike";
                else if($tableOperator == self::OPTION_NOT_ENDING_WITH)    $tableOperator = "notLike";

                return $qb->expr()->$tableOperator($tableColumn, ":$fieldID");

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

    protected function getQueryBuilder(array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null): ?QueryBuilder
    {
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

                    // In case of association field, compare value directly
                    if ($this->getClassMetadata()->hasAssociation($entryID)) $qb->andWhere($queryExpr);
                    // If standard field, check for partial information
                    else $expr[] = $queryExpr;
                }

                $expr = empty($expr) ? null : $qb->expr()->orX(...$expr);

            } else {

                //Default query builder
                $expr = $this->buildQueryExpr($qb, $field, $fieldValue);
            }

            if($expr !== null) {

                // Apply logical operator (if needed)
                switch ($this->getSeparator()) {

                    case self::SEPARATOR_OR: $qb->orWhere($expr);
                        break;

                    default:
                    case self::SEPARATOR_AND: $qb->andWhere($expr);
                        break;
                }
            }
        }

        // Sort result by group
        if($groupBy) $qb->select(self::ALIAS_ENTITY . " as entity");
        else $qb->select(self::ALIAS_ENTITY);

        $qb = $this->orderBy($qb, $orderBy);
        $qb = $this->groupBy($qb, $groupBy);
        
        return $qb;
    }
    
    protected function getQuery(array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null): ?Query
    {
        $qb = $this->getQueryBuilder($criteria, $orderBy, $groupBy, $limit, $offset);
        $query = $qb->getQuery();

        if($query->isCacheable()) {

            $entityName     = $this->classMetadata->getName();
            $rootEntityName = $this->classMetadata->rootEntityName;
            
            if($entityName == $rootEntityName && class_implements_interface($entityName, TranslatableInterface::class)) {

                $qb->leftJoin(self::ALIAS_ENTITY.'.translations', self::ALIAS_TRANSLATIONS);
                $qb->addSelect(self::ALIAS_TRANSLATIONS);

                $query = $qb->getQuery();
            }
        }

        return $query;
    }

    protected function getQueryWithCount(array $criteria, ?string $mode = "", ?array $orderBy = null, $groupBy = null)
    {
        if($mode == self::COUNT_ALL) $mode = "";
        if($mode && $mode != self::COUNT_DISTINCT)
            throw new Exception("Unexpected \"mode\" provided: \"". $mode."\"");

        $column = $this->getAlias($this->getColumn());
        $qb = $this->getQueryBuilder($criteria, $orderBy, $groupBy);
        $this->innerJoin($qb, $column);

        $column = self::ALIAS_ENTITY . ($this->getClassMetadata()->hasAssociation($column) ? "_".$column : "");
        $qb->select('COUNT('.trim($mode.' '.$column).') AS count');
        
        $this->orderBy($qb, $orderBy);
        $this->groupBy($qb, $groupBy);

        return $qb->getQuery();
    }
    
    protected function getQueryWithLength(array $criteria = [], $orderBy = null, $groupBy = null, $limit = null, $offset = null)
    {
        $column = $this->getAlias($this->getColumn());
        $column = self::ALIAS_ENTITY . ($this->getClassMetadata()->hasAssociation($column) ? "_".$column : "");

        $qb = $this->getQueryBuilder($criteria, $orderBy, $groupBy, $limit, $offset);
        $this->innerJoin($qb, $column);

        $qb->select("LENGTH(".self::ALIAS_ENTITY.".".$column.") as length");
        
        $this->orderBy($qb, $orderBy);
        $this->groupBy($qb, $groupBy);

        return $qb->getQuery();
    }

    protected function groupBy($qb, $groupBy)
    {
        if($groupBy) {

            $column = explode("\\", $this->getClassMetadata()->getName());
            $column = lcfirst(end($column));

            if(is_string($groupBy)) $groupBy = [$groupBy];
            if(!is_array($groupBy)) throw new Exception("Unexpected \"groupBy\" argument type provided \"".gettype($groupBy)."\"");

            foreach ($groupBy as $name => $value) {

                $alias = str_replace(".", "_", $value);
                $value = implode(".", array_map(fn ($value) => $this->getAlias($value), explode(".", $value)));

                $firstValue = explode(".", $value)[0] ?? $value;
                $groupBy[$name] = self::ALIAS_ENTITY . ($this->getClassMetadata()->hasAssociation($firstValue) ? "_" : ".") . $value;
                
                if($groupBy[$name] == self::ALIAS_ENTITY.".".$alias) $qb->addSelect($groupBy[$name]);
                else $qb->addSelect("(".$groupBy[$name].") AS ".$alias);

                $qb = $this->innerJoin($qb, $alias);
            }

            $qb->groupBy(implode(",", $groupBy));
        }

        return $qb;
    }

    protected $innerJoinList = [];
    protected function innerJoin($qb, $innerJoin)
    {
        if(in_array($innerJoin, $this->innerJoinList[spl_object_hash($qb)])) return $qb;
        if ($this->getClassMetadata()->hasAssociation($innerJoin)) {

            $qb->innerJoin(self::ALIAS_ENTITY.".".$innerJoin, self::ALIAS_ENTITY."_".$innerJoin);
            $this->innerJoinList[spl_object_hash($qb)][] = $innerJoin;
        }

        return $qb;
    }

    protected function orderBy($qb, $orderBy)
    {
        if($orderBy) {

            $column = explode("\\", $this->getClassMetadata()->getName());
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
                
                    $formattedName = self::ALIAS_ENTITY . ($this->getClassMetadata()->hasAssociation($entity) ? "_" : ".") . $name;
                    $qb = $this->innerJoin($qb, $entity);
                }

                $orderBy = $first ? "orderBy" : "addOrderBy";
                if($isRandom)
                    $qb->orderBy('RAND()');
                else if(is_array($value)) 
                    $qb->add($orderBy, "FIELD(".$formattedName.",".implode(",",$value).")");
                else 
                    $qb->$orderBy($formattedName, $value);

                $first = false;
            }
        }

        return $qb;
    }
}
