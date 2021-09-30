<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use ReflectionException;
use ReflectionProperty;

/**
 * Class EntityHierarchy
 * package Base\Annotations\Annotation\EntityHierarchy
 *
 * @Annotation
 * @Target({"CLASS", "PROPERTY"})
 * @Attributes({
 *   @Attribute("column", type = "string"),
 *   @Attribute("alias" , type = "string")
 * })
 */

class ColumnAlias extends AbstractAnnotation
{
    /** @Required */
    private string $value;

    public function __construct(array $data)
    {
        $this->column = $data["column"] ?? "";
        $this->alias  = $data["alias"]  ?? "";
    }

    public function supports(ClassMetadata $classMetadata, string $target, ?string $targetValue = null, $entity = null):bool
    {
        return ($target == AnnotationReader::TARGET_CLASS || $target == AnnotationReader::TARGET_PROPERTY);
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target = null, string $targetValue = null)
    {
        if($target == "property") $alias = $targetValue;
        else $alias = $this->alias;

        if(!property_exists($classMetadata->getName(), $alias))  throw new Exception("Invalid alias property \"$alias\" provided in annotation of class ".$classMetadata->getName());
        if(!property_exists($classMetadata->getName(), $this->column)) throw new Exception("Invalid column property \"$this->column\" provided in annotation of class ".$classMetadata->getName());
        
        $aliasOrm  = $classMetadata->hasAssociation($alias) || $classMetadata->hasField($alias);
        if($aliasOrm) throw new Exception("Alias variable \"$alias\" cannot be an ORM variable.");
        $columnOrm = $classMetadata->hasAssociation($this->column) || $classMetadata->hasField($this->column);
        if(!$columnOrm) throw new Exception("Column variable \"$this->column\" must be an ORM variable.");

        $classMetadata->fieldNames[$alias] = $this->column;
    }
    
    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $alias  = $property ?? $this->alias;
        $column = $this->column;

        $fn = function() use ($alias, $column) {

            $this->$alias =& $this->$column; // Bind variable together..
            return $this;
        };

        $fnClosure = \Closure::bind($fn, $entity, get_class($entity));
        $fnClosure();
    }
}