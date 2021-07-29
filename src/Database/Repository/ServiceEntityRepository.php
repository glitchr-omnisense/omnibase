<?php

namespace Base\Database\Repository;

use BadMethodCallException;
use Base\Entity\Thread;
use Base\Entity\Thread\Tag;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;

/**
 * @method Thread|null find($id, $lockMode = null, $lockVersion = null)
 * @method Thread|null findOneBy(array $criteria, array ?array $orderBy = null)
 * @method Thread[]    findAll()
 * @method Thread[]    findBy(array $criteria, array ?array $orderBy = null, $limit = null, $offset = null)
 */
class ServiceEntityRepository extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository
{
    public const OPTION_WITH_ROUTE       = "WithRoute";
    public const OPTION_PARTIAL          = "Partial";
    public const OPTION_MODEL            = "Model";
    public const OPTION_INSENSITIVE = "Insensitive";

    public const SEPARATOR     = ":";
    public const OPERATOR_AND  = "And";
    public const OPERATOR_OR   = "Or";

    public array $criteria = [];
    public array $options  = [];

    public function resetCriteria()
    {
        $this->criteria = [];
    }

    public function addCriteria(string $by, $value)
    {
        $index = 0;
        while( array_key_exists($by . self::SEPARATOR . $index, $this->criteria) )
            $index++;

        $this->criteria[$by . self::SEPARATOR . $index] = $value;
        return $by . self::SEPARATOR . $index;
    }

    public function resetCustomOptions()
    {
        $this->options = [];
    }

    public function addCustomOption(string $id, $option)
    {
        if(! array_key_exists($id, $this->criteria))
            throw new Exception("Criteria ID \"$id\" not found in criteria list.. wrong usage? use \"addCriteria\" first.");

        if(!array_key_exists($id, $this->options))
            $this->options[$id] = [];

        $this->options[$id][] = $option;
    }

    protected $operator;
    public function setOperator($operator)
    {
        $this->operator = $operator;
        return $this;
    }

    public function getOperator()
    {
        return $this->operator;
    }

    public function parseMethod($method, $arguments)
    {
        $this->resetCriteria();
        $this->resetCustomOptions();

        // Make sure arguments have at least 5 parameters :
        // Head parameters (depends on the method name) + default ones
        // Default parameters:
        // - 0: criteria
        // - 1: orderBy
        // - 2: limit
        // - 3: offset

        $arguments = array_pad($arguments, 4, null);

        // Definition of the returned values
        $newMethod = null;
        $newArguments = array_pad([], 4, null);

        // TODO: Safety check in dev mode only (maybe..)
        $classMetadata  = $this->getClassMetadata($this->getEntityName());
        foreach($classMetadata->getFieldNames() as $field) {

            if (str_contains($field, self::OPTION_WITH_ROUTE ) ||
                str_contains($field, self::OPTION_MODEL )      ||
                str_contains($field, self::OPTION_PARTIAL)     ||
                str_contains($field, self::OPTION_INSENSITIVE) ||
                str_contains($field, self::OPERATOR_AND )      ||
                str_contains($field, self::OPERATOR_OR )      ||
                str_contains($field, self::SEPARATOR ))
                throw new Exception(
                    "\"".$this->getEntityName(). "\" entity has a field called \"$field\". ".
                    "This is unfortunate, because this word is used to customize DQL queries. ".
                    "Please build your own DQL query or change your database field name"
                );
        }

        // Find and sort the "With" list
        $withs = array_filter([
            self::OPTION_WITH_ROUTE => strpos($method, self::OPTION_WITH_ROUTE)],
            fn ($value)  => ($value !== false)
        );
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
        if (strpos($method, 'findBy') === 0) {
            $newMethod = "findBy";
            $byNames = substr($method, strlen("findBy"));
        }

        if (strpos($method, 'findOneBy') === 0) {
            $newMethod = "findOneBy";
            $byNames  = substr($method, strlen("findOneBy"));
        }

        if (strpos($method, 'count') === 0) {
            $newMethod = "countBy";
            $byNames  = substr($method, strlen("countBy"));
        }

        if(empty($newMethod)) {

            throw new BadMethodCallException(sprintf(
                'Undefined method "%s". The method name must start with ' .
                'either findBy, findOneBy or countBy!',
                $method
            ));
        }

        // Divide in case of multiple variable
        // Only AND operation is tolerated.. because of obvious logical ambiguity.
        if (str_contains($byNames, self::OPERATOR_AND ) && str_contains($byNames, self::OPERATOR_OR )) {

            throw new Exception("\"".$byNames. "\" method gets an AND/OR ambiguity");

        } else if (str_contains($byNames, self::OPERATOR_OR)) {

            $this->setOperator(self::OPERATOR_OR);
            $byNames = explode(self::OPERATOR_OR, $byNames);

        } else {

            $this->setOperator(self::OPERATOR_AND);
            $byNames = explode(self::OPERATOR_AND, $byNames);
        }

        $methodBak = $method;
        foreach($byNames as $by) {

            $oldBy = null;

            $isInsensitive = $isPartial = false;
            while ($oldBy != $by) {

                $oldBy = $by;

                $option = null;
                if ( str_starts_with($by, self::OPTION_PARTIAL) )
                    $option = self::OPTION_PARTIAL;
                else if ( str_starts_with($by, self::OPTION_INSENSITIVE) )
                    $option = self::OPTION_INSENSITIVE;

                switch($option) {
                    case self::OPTION_PARTIAL:
                        $isPartial = true;
                        break;
                    case self::OPTION_INSENSITIVE:
                        $isInsensitive = true;
                        break;
                }

                if($option) {

                    $method = substr($method, strlen($option), strlen($method));
                    $by = substr($by, strlen($option), strlen($by));
                }
            }

            // First check if WithRoute special argument is found..
            // This argument will retrieve the value of the corresponding route parameter
            // and use it in the query
            if (str_ends_with($by, self::OPTION_WITH_ROUTE)) {

                $method = substr($method, 0, strpos($method, self::OPTION_WITH_ROUTE));
                $by = substr($by, 0, strlen($by) - strlen(self::OPTION_WITH_ROUTE));
                $key       = array_shift($arguments);

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
                    $this->addCriteria($by, $fieldValue);
                }

            } else {

                if ($by == self::OPTION_MODEL) {

                    $method = substr($method, 0, strpos($method, self::OPTION_MODEL));
                    $by = lcfirst($by);

                    $modelCriteria = [];
                    $model = array_shift($arguments);

                    $reflClass = new ReflectionClass(get_class($model));
                    foreach ($reflClass->getProperties() as $field) {

                        $fieldName = $field->getName();

                        if (!$field->isInitialized($model)) continue;
                        if (!($fieldValue = $field->getValue($model)) ) continue;

                        if ($classMetadata->hasAssociation($fieldName)) {

                            $associationField = $classMetadata->getAssociationMapping($fieldName);
                            if (!array_key_exists("targetEntity", $associationField) || $field->getType() != $associationField["targetEntity"])
                                throw new Exception("Invalid association mapping \"$fieldName\" found (found \"".$field->getType()."\", expected type \"". $associationField["targetEntity"]."\") in \"" . $this->getEntityName() . "\" entity, \"" . $reflClass->getName() . " cannot be applied\"");

                        } else if(!$classMetadata->hasField($fieldName))
                            throw new Exception("No field \"$fieldName\" (or association mapping) found in \"".$this->getEntityName(). "\" entity, \"".$reflClass->getName()." cannot be applied\"");

                        if (( $fieldValue = $field->getValue($model) ))
                            $modelCriteria[$fieldName] = $fieldValue;
                    }

                    if(!empty($modelCriteria)) {

                        $id = $this->addCriteria($by, $modelCriteria);
                        if ($isPartial) $this->addCustomOption($id, self::OPTION_PARTIAL);
                        if ($isInsensitive) $this->addCustomOption($id, self::OPTION_INSENSITIVE);
                    }

                } else {

                    $by = lcfirst($by);

                    $fieldValue = array_shift($arguments);
                    if($fieldValue) {

                        $id = $this->addCriteria($by, $fieldValue);
                        if ($isPartial) $this->addCustomOption($id, self::OPTION_PARTIAL);
                        if ($isInsensitive) $this->addCustomOption($id, self::OPTION_INSENSITIVE);
                    }
                }
            }
        }

        // Index definition:
        // "criteria"  = argument #0, after removal of the head parameters
        $newArguments    = $arguments;
        $newArguments[0] = array_merge($newArguments[0] ?? [], $this->criteria ?? []);

        // Shaped return
        return [$newMethod, $newArguments];
    }

    public function flush()
    {
        return $this->getEntityManager()->flush();
    }

    public function __call($method, $arguments)
    {
        list($method, $arguments) = $this->parseMethod($method, $arguments);
        return $this->$method(...$arguments);
    }

    public function findOneBy(array $criteria = [], ?array $orderBy = null)
    {
        $findBy = $this->findBy($criteria, $orderBy, 1, null) ?? [];
        return $findBy[0] ?? null;
    }

    public function findBy   (array $criteria = [], ?array $orderBy = null, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('t')
            ->setMaxResults($limit ? $limit : null)
            ->setFirstResult($offset ? $offset : null);

        foreach ($orderBy ?? [] as $name => $value)
            $qb->orderBy("t.${name}", $value);

        $classMetadata  = $this->getClassMetadata($this->getEntityName());
        foreach ($criteria as $field => $fieldValue) {

            $field     = explode(self::SEPARATOR, $field);
            $fieldName = $field[0];

            if($fieldName == lcfirst(self::OPTION_MODEL)) {

                $expr = [];
                foreach ($fieldValue as $entryID => $entryValue) {

                    $newField = [];
                    foreach ($field as $key => $value)
                        $newField[$key] = $value;

                    array_unshift($newField, $entryID);

                    $queryExpr = $this->buildQueryExpr($qb, $classMetadata, $newField, $entryValue);

                    // In case of association field, compare value directly
                    if ($classMetadata->hasAssociation($entryID)) $qb->andWhere($queryExpr);
                    // If standard field, check for partial information
                    else $expr[] = $queryExpr;
                }

                $expr = $qb->expr()->orX(...$expr);

            } else {

                $expr = $this->buildQueryExpr($qb, $classMetadata, $field, $fieldValue);
            }

            switch ($this->getOperator()) {
                case self::OPERATOR_OR: $qb->orWhere($expr);
                    break;

                default:
                case self::OPERATOR_AND: $qb->andWhere($expr);
                    break;
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function getCustomOption(string $id)
    {
        return $this->options[$id] ?? [];
    }

    public function findCustomOption(string $id, string $option)
    {
        return in_array($option, $this->getCustomOption($id));
    }

    public function buildQueryExpr(QueryBuilder $qb, ClassMetadata $classMetadata, $field, $fieldValue)
    {
        $fieldName      = $field[0];
        $fieldID        = implode("_", $field);
        $fieldRoot = implode(self::SEPARATOR, array_slice($field, count($field) - 2, 2));

        $isPartial     = $this->findCustomOption($fieldRoot, self::OPTION_PARTIAL);
        $isInsensitive = $this->findCustomOption($fieldRoot, self::OPTION_INSENSITIVE);

        // Prepare field parameter
        $qb->setParameter($fieldID, $fieldValue);

        // Regular field: string, datetime..
        if (!$classMetadata->hasAssociation($fieldName))
            $tableColumn = "t.${fieldName}";

        else { // Relationship field: ManyToMany,ManyToOne, OneToMany..

            $tableColumn = "t_${fieldName}";
            $qb->innerJoin("t.${fieldName}", $tableColumn);
        }

        if ($isInsensitive) $tableColumn = "lower(" . $tableColumn . ")";
        if ($isPartial) {

            if (is_array($fieldValue)) {

                $expr = [];
                foreach ($fieldValue as $entryID => $entry) {

                    $entryID = $fieldID . "_" . $entryID;
                    $qb->setParameter($entryID, ($isInsensitive ? strtolower($entry) : $entry));

                    $expr[] = $qb->expr()->like($tableColumn, ":${entryID}");
                }

                $expr = $qb->expr()->orX(...$expr);

            } else if ($classMetadata->hasAssociation($fieldName)) {

                $expr = "${tableColumn} = :${fieldID}";

            } else {

                $expr = "${tableColumn} LIKE :${fieldID}";
            }

        } else {

            if (is_array($fieldValue)) $expr = "${tableColumn} IN (:${fieldID})";
            else $expr = "${tableColumn} = :${fieldID}";
        }

        return $expr;
    }
}
