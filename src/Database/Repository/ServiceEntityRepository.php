<?php

namespace Base\Database\Repository;

use BadMethodCallException;
use Base\Entity\Thread;
use Base\Entity\Thread\Tag;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;

/**
 * @method Thread|null find($id, $lockMode = null, $lockVersion = null)
 * @method Thread|null findOneBy(array $criteria, array ?array $orderBy = null, $groupBy = null)
 * @method Thread[]    findAll(?array $orderBy = null, $groupBy = null)
 * @method Thread[]    findBy(array $criteria, array ?array $orderBy = null, $groupBy = null, $limit = null, $offset = null)
 */
class ServiceEntityRepository extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository
{
    public const OPTION_INSENSITIVE  = "Insensitive";
    public const OPTION_WITH_ROUTE   = "WithRoute";
    public const OPTION_PARTIAL      = "Partial";
    public const OPTION_MODEL        = "Model";

    public const OPTION_GREATER      = "GreaterThan";
    public const OPTION_GREATER_EQUAL = "GreaterEqualTo";
    public const OPTION_LOWER        = "LowerThan";
    public const OPTION_LOWER_EQUAL   = "LowerEqualTo";
    public const OPTION_EQUAL        = "EqualTo";
    public const OPTION_NOT_EQUAL    = "NotEqualTo";

    public const SEPARATOR     = ":";
    public const OPERATOR_AND  = "And";
    public const OPERATOR_OR   = "Or";

    public const MODE_ALL = "ALL";
    public const MODE_DISTINCT = "DISTINCT";

    protected $classMetaData = null;

    protected $operator = null;
    protected $column = null;
    protected array $criteria = [];
    protected array $options  = [];

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
    
    protected function getAlias($alias) { 

        $this->getClassMetadata()->fieldNames[$alias] ?? $alias;
        return $this->getClassMetadata()->fieldNames[$alias] ?? $alias;
    }
    
    protected function getColumn() { return $this->column; }
    
    protected function setColumn(string $column)
    {
        $this->column = $column;
        return $this;
    }

    protected function getOperator() { return $this->operator; }
    protected function setOperator($operator)
    {
        $this->operator = $operator;
        return $this;
    }

    protected function addCriteria(string $by, $value)
    {
        if(empty($by)) 
            throw new Exception("Tried to add unnamed criteria");

        $index = 0;
        while( array_key_exists($by . self::SEPARATOR . $index, $this->criteria) )
            $index++;

        $this->criteria[$by . self::SEPARATOR . $index] = $value;
        return $by . self::SEPARATOR . $index;
    }

    public function flush() { return $this->getEntityManager()->flush(); }
    
    protected function stripByFront($method, $by, $option)
    {
        $method = substr($method, strlen($option), strlen($method));
        $by = substr($by, strlen($option), strlen($by));
        return [$method, $by];
    }

    protected function stripByEnd($method, $by, $option)
    {
        $method = substr($method, 0, strpos($method, $option));
        $by     = substr($by, 0, strlen($by) - strlen($option));
        return [$method, $by];
    }

    public function __call($method, $arguments)
    {
        // Parse method and call it
        list($method, $arguments) = $this->parseMethod($method, $arguments);
        $ret = $this->$method(...$arguments);

        // Reset internal variables
        $this->criteria = [];
        $this->options = [];
        $this->operator = null;

        return $ret;
    }

    protected function parseMethod($method, $arguments)
    {
        // Head parameters (depends on the method name) + default ones
        // Default parameters:
        // - 0: criteria
        // - 1: orderBy
        // - 2: groupBy
        // - 3: limit
        // - 4: offset
        
        // Definition of the returned values
        $newMethod = null;
        $newArguments = [];

        // TODO: Safety check in dev mode only (maybe..)
        foreach($this->getClassMetadata()->getFieldNames() as $field) {

            if (str_contains($field, self::OPTION_WITH_ROUTE )  ||
                str_contains($field, self::OPTION_MODEL      )  ||
                str_contains($field, self::OPTION_PARTIAL    )  ||
                str_contains($field, self::OPTION_INSENSITIVE)  ||

                str_contains($field, self::OPTION_LOWER)        ||
                str_contains($field, self::OPTION_GREATER)      ||
                str_contains($field, self::OPTION_LOWER_EQUAL)   ||
                str_contains($field, self::OPTION_GREATER_EQUAL) ||

                str_contains($field, self::OPTION_EQUAL)        ||
                str_contains($field, self::OPTION_NOT_EQUAL)    ||

                str_contains($field, self::OPERATOR_AND      )  ||
                str_contains($field, self::OPERATOR_OR       )  ||
                str_contains($field, self::SEPARATOR         ))

                throw new Exception(
                    "\"".$this->getEntityName(). "\" entity has a field called \"$field\". ".
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
        if (preg_match('/^(find(?:One)?By)(.*)/', $method, $matches)) {

            $newMethod = $matches[1] ?? "";
            $byNames   = $matches[2] ?? "";
            
        } else if (preg_match('/^(distinctCount|count)(?:For([^By]*))?(?:By){0,1}(.*)/', $method, $matches)) {
            
            $newMethod = $matches[1] ?? "";
            $byNames   = $matches[3] ?? "";

            $this->setColumn(lcfirst($matches[2]) ?? null);

        } else if (preg_match('/^(lengthOf)([^By]+)(?:By){0,1}(.*)/', $method, $matches)) {
            
            $newMethod = $matches[1] ?? "";
            $byNames   = $matches[3] ?? "";

            $this->setColumn(lcfirst($matches[2]) ?? null);

        } else {

            throw new Exception(sprintf(
                'Undefined method "%s". The method name must start with ' .
                'either findBy, findOneBy, distinctCount, count, lengthOf!',
                $method
            ));
        }

        // Reveal obvious logical ambiguities..
        if (str_contains($byNames, self::OPERATOR_AND ) && str_contains($byNames, self::OPERATOR_OR ))
            throw new Exception("\"".$byNames. "\" method gets an AND/OR ambiguity");

        // Desintangle logical operators
        if (str_contains($byNames, self::OPERATOR_OR)) {

            $this->setOperator(self::OPERATOR_OR);
            $byNames = explode(self::OPERATOR_OR, $byNames);

        } else {

            $this->setOperator(self::OPERATOR_AND);
            $byNames = explode(self::OPERATOR_AND, $byNames);
        }

        $methodBak = $method;
        foreach($byNames as $by) {

            $oldBy = null;
            
            $operator = self::OPTION_EQUAL;
            $isInsensitive = $isPartial = false;
            while ($oldBy != $by) {

                $oldBy = $by;

                $option = null;
                if ( str_starts_with($by, self::OPTION_PARTIAL) )
                    $option = self::OPTION_PARTIAL;
                else if ( str_starts_with($by, self::OPTION_INSENSITIVE) )
                    $option = self::OPTION_INSENSITIVE;
            
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
            if (str_ends_with($by, self::OPTION_WITH_ROUTE)) {

                list($method, $by) = $this->stripByEnd($method, $by, self::OPTION_WITH_ROUTE);
                $key    = array_shift($arguments);

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

            } else {

                if ($by == self::OPTION_MODEL) {

                    list($method, $_) = $this->stripByEnd($method, $by, $option);
                    $method = substr($method, 0, strpos($method, self::OPTION_MODEL));
                    $by = lcfirst($by);

                    $modelCriteria = [];
                    $model = array_shift($arguments);

                    $reflClass = new ReflectionClass(get_class($model));
                    foreach ($reflClass->getProperties() as $field) {

                        $fieldName = $field->getName();

                        if (!$field->isInitialized($model)) continue;
                        if (!($fieldValue = $field->getValue($model)) ) continue;

                        if ($this->getClassMetadata()->hasAssociation($fieldName)) {

                            $associationField = $this->getClassMetadata()->getAssociationMapping($fieldName);
                            if (!array_key_exists("targetEntity", $associationField) || $field->getType() != $associationField["targetEntity"])
                                throw new Exception("Invalid association mapping \"$fieldName\" found (found \"".$field->getType()."\", expected type \"". $associationField["targetEntity"]."\") in \"" . $this->getEntityName() . "\" entity, \"" . $reflClass->getName() . " cannot be applied\"");

                        } else if(!$this->getClassMetadata()->hasField($fieldName))
                            throw new Exception("No field \"$fieldName\" (or association mapping) found in \"".$this->getEntityName(). "\" entity, \"".$reflClass->getName()." cannot be applied\"");

                        if (( $fieldValue = $field->getValue($model) ))
                            $modelCriteria[$fieldName] = $fieldValue;
                    }

                    if(!empty($modelCriteria)) {

                        $id = $this->addCriteria($by, $modelCriteria);
                        if ($isPartial) $this->addCustomOption($id, self::OPTION_PARTIAL);
                        if ($isInsensitive) $this->addCustomOption($id, self::OPTION_INSENSITIVE);
                        
                        if ($operator == self::OPTION_EQUAL || $operator == self::OPTION_NOT_EQUAL) $this->addCustomOption($id, $operator);
                        else throw new Exception("Unexpected operator \"".$operator."\" found in model definition");
                    }

                } else {

                    $by = lcfirst($by);

                    $fieldValue = array_shift($arguments);
                    if($fieldValue) {

                        $id = $this->addCriteria($by, $fieldValue);
                        if ($isPartial) $this->addCustomOption($id, self::OPTION_PARTIAL);
                        if ($isInsensitive) $this->addCustomOption($id, self::OPTION_INSENSITIVE);
                        if ($operator) $this->addCustomOption($id, $operator);
                    }
                }
            }
        }

        // Index definition:
        // "criteria"  = argument #0, after removal of head parameters
        foreach($arguments as $i => $arg)
            $newArguments[$i] = $arg;

        $newArguments[0] = array_merge($newArguments[0] ?? [], $this->criteria ?? []);
        
        // Shaped return
        return [$newMethod, $newArguments];
    }

    protected function buildQueryExpr(QueryBuilder $qb, $field, $fieldValue)
    {
        $fieldName = $this->getAlias($field[0]);
        $fieldID   = implode("_", $field);
        $fieldRoot = implode(self::SEPARATOR, array_slice($field, count($field) - 2, 2));

        $isPartial     = $this->findCustomOption($fieldRoot, self::OPTION_PARTIAL);
        $isInsensitive = $this->findCustomOption($fieldRoot, self::OPTION_INSENSITIVE);
        $tableOperator = 
            ($this->findCustomOption ($fieldRoot, self::OPTION_EQUAL)         ? self::OPTION_EQUAL :
            ($this->findCustomOption ($fieldRoot, self::OPTION_NOT_EQUAL)     ? self::OPTION_NOT_EQUAL :
            ($this->findCustomOption ($fieldRoot, self::OPTION_GREATER)       ? self::OPTION_GREATER :
            ($this->findCustomOption ($fieldRoot, self::OPTION_GREATER_EQUAL) ? self::OPTION_GREATER_EQUAL :
            ($this->findCustomOption ($fieldRoot, self::OPTION_LOWER)         ? self::OPTION_LOWER :
            ($this->findCustomOption ($fieldRoot, self::OPTION_LOWER_EQUAL)   ? self::OPTION_LOWER_EQUAL : null))))));

        if(is_array($tableOperator))
            throw new Exception("Too many operator requested for \"$fieldName\": ".implode(",", $tableOperator));

        // Prepare field parameter
        $qb->setParameter($fieldID, $fieldValue);

        // Regular field: string, datetime..
        if (!$this->getClassMetadata()->hasAssociation($fieldName))
            $tableColumn = "t.${fieldName}";

        else { // Relationship field: ManyToMany,ManyToOne, OneToMany..

            $tableColumn = "t_${fieldName}";
            $qb->innerJoin("t.${fieldName}", $tableColumn);
        }

        if ($isInsensitive) $tableColumn = "LOWER(" . $tableColumn . ")";
        if ($isPartial) {

            if (is_array($fieldValue)) {

                $expr = [];
                foreach ($fieldValue as $entryID => $entry) {

                    $entryID = $fieldID . "_" . $entryID;
                    $qb->setParameter($entryID, ($isInsensitive ? strtolower($entry) : $entry));

                    $expr[] = $qb->expr()->like($tableColumn, ":${entryID}");
                }

                $expr = $qb->expr()->orX(...$expr);

            } else if ($this->getClassMetadata()->hasAssociation($fieldName)) {

                     if($tableOperator == self::OPTION_EQUAL)         $tableOperator = "=";
                else if($tableOperator == self::OPTION_NOT_EQUAL)     $tableOperator = "!=";
                else throw new Exception("Invalid operator for association field \"$fieldName\": ".$tableOperator);

                $expr = "${tableColumn} ${tableOperator} :${fieldID}";

            } else {

                     if($tableOperator == self::OPTION_EQUAL)     $tableOperator = "LIKE";
                else if($tableOperator == self::OPTION_NOT_EQUAL) $tableOperator = "NOT LIKE";
                else throw new Exception("Invalid operator for field \"$fieldName\": ".$tableOperator);

                $expr = "${tableColumn} ${tableOperator} :${fieldID}";
            }

        } else if (is_array($fieldValue)) {

                 if($tableOperator == self::OPTION_EQUAL)     $tableOperator = "IN";
            else if($tableOperator == self::OPTION_NOT_EQUAL) $tableOperator = "NOT IN";
            else throw new Exception("Invalid operator for field \"$fieldName\": ".$tableOperator);

            $expr = "${tableColumn} ${tableOperator} (:${fieldID})";
            
        } else {
            
                 if($tableOperator == self::OPTION_EQUAL)         $tableOperator = "=";
            else if($tableOperator == self::OPTION_NOT_EQUAL)     $tableOperator = "!=";
            else if($tableOperator == self::OPTION_GREATER)       $tableOperator = ">";
            else if($tableOperator == self::OPTION_GREATER_EQUAL) $tableOperator = ">=";
            else if($tableOperator == self::OPTION_LOWER)         $tableOperator = "<";
            else if($tableOperator == self::OPTION_LOWER_EQUAL)   $tableOperator = "<=";
            else throw new Exception("Invalid operator for field \"$fieldName\": ".$tableOperator);

            $expr = "${tableColumn} $tableOperator :${fieldID}";
        }

        return $expr;
    }

    public function getQueryBuilder(array $criteria = [], ?array $orderBy = null, $groupBy = null, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('t')
                   ->setMaxResults($limit ?? null)
                   ->setFirstResult($offset ?? null);

        foreach ($orderBy ?? [] as $name => $value)
            $qb->orderBy("t.".$this->getAlias($name), $value);

        // Prepare criteria variable
        foreach ($criteria as $field => $fieldValue) {

            $field     = explode(self::SEPARATOR, $field);
            $fieldName = $field[0];

            if($fieldValue instanceof PersistentCollection)
                 throw new Exception("You passed a PersistentCollection for field \"".$fieldName."\"");

            // Handle partial entity/model input criteria
            if($fieldName == lcfirst(self::OPTION_MODEL)) {
                
                $expr = [];
                foreach ($fieldValue as $entryID => $entryValue) {

                    $newField = [];
                    foreach ($field as $key => $value) $newField[$key] = $value;
                    array_unshift($newField, $entryID);

                    $queryExpr = $this->buildQueryExpr($qb, $newField, $entryValue);

                    // In case of association field, compare value directly
                    if ($this->getClassMetadata()->hasAssociation($entryID)) $qb->andWhere($queryExpr);
                    // If standard field, check for partial information
                    else $expr[] = $queryExpr;
                }

                $expr = $qb->expr()->orX(...$expr);

            } else {

                //Default query builder
                $expr = $this->buildQueryExpr($qb, $field, $fieldValue);
            }

            // Set logical operator
            switch ($this->getOperator()) {

                case self::OPERATOR_OR: $qb->orWhere($expr);
                    break;

                default:
                case self::OPERATOR_AND: $qb->andWhere($expr);
                    break;
            }
        }

        // Sort result by group
        if($groupBy) $qb->select("t as entity");
        else $qb->select("t");
        
        $qb = $this->groupBy($qb, $groupBy);
        return $qb;
    }

    // public function find($id, $lockMode = null, $lockVersion = null)
    // {
    //     return $this->_em->find($this->_entityName, $id, $lockMode, $lockVersion);
    // }

    // public function findAll(?array $orderBy = null, $groupBy = null)
    // {
    //     return $this->findBy([], $orderBy = null, $groupBy = null);
    // }
    
    protected function groupBy($qb, $groupBy)
    {
        if($groupBy) {

            $column = explode("\\", $this->getEntityName());
            $column = lcfirst(end($column));

            if(is_string($groupBy)) $groupBy = [$groupBy];
            if(!is_array($groupBy)) throw new Exception("Unexpected \"groupBy\" argument type provided \"".gettype($groupBy)."\"");

            foreach ($groupBy ?? [] as $name => $value) {

                $alias = str_replace(".", "_", $value);
                $value = implode(".", array_map(fn ($value) => $this->getAlias($value), explode(".", $value)));

                $firstValue = explode(".", $value)[0] ?? $value;
                $groupBy[$name] = $this->getClassMetadata()->hasAssociation($firstValue) ? "t_".$value : "t.".$value;
                
                if($groupBy[$name] == "t.".$alias) $qb->addSelect($groupBy[$name]);
                else $qb->addSelect("(".$groupBy[$name].") AS ".$alias);
            }

            $qb->groupBy(implode(",", $groupBy));
        }

        return $qb;
    }
    public function findOneBy(array $criteria = [], ?array $orderBy = null, $groupBy = null                               ) { return $this->findBy($criteria, $orderBy, $groupBy, 1, null)[0] ?? null; }
    public function findBy   (array $criteria = [], ?array $orderBy = null, $groupBy = null, $limit = null, $offset = null) { return $this->getQueryBuilder($criteria, $orderBy, $groupBy, $limit, $offset)->getQuery()->getResult() ?? []; }

    public function distinctCount(array $criteria, $groupBy = null) { return $this->count($criteria, self::MODE_DISTINCT, $groupBy); }
    public function count(array $criteria, ?string $mode = "", ?array $orderBy = null, $groupBy = null)
    {
        if($mode == self::MODE_ALL) $mode = "";
        if($mode && $mode != self::MODE_DISTINCT)
            throw new Exception("Unexpected \"mode\" provided: \"". $mode."\"");
        
        $column = $this->getAlias($this->getColumn());
        $column = ($this->getClassMetadata()->hasAssociation($column) ? "t_".$column : "t");
        
        if($groupBy) $groupBy = implode(",", $groupBy);
        $qb = $this->getQueryBuilder($criteria, $orderBy, $groupBy);
        $qb->select('COUNT('.trim($mode.' '.$column).') AS count');
        
        $column = $this->getAlias($this->getColumn());
        if ($this->getClassMetadata()->hasAssociation($column))
            $qb->innerJoin("t.".$column, "t_".$column);

        $qb = $this->groupBy($qb, $groupBy);

        $fnResult = ($groupBy ? "getResult" : "getSingleScalarResult");
        return $qb->getQuery()->$fnResult();
    }
    
    public function lengthOf(array $criteria = [], ?array $orderBy = null, $groupBy = null, $limit = null, $offset = null)
    {
        $column = $this->getAlias($this->getColumn());
        $column = ($this->getClassMetadata()->hasAssociation($column) ? "t_".$column : "t");

        $qb = $this->getQueryBuilder($criteria, $orderBy, $groupBy, $limit, $offset);
        $qb->select("LENGTH(t.".$column.") as length");
        $qb = $this->groupBy($qb, $groupBy);

        return $qb->getQuery()->getResult() ?? 0;
    }
}
