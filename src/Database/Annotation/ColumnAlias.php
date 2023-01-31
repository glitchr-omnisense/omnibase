<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * @Annotation
 * @Target({"CLASS", "PROPERTY"})
 * @Attributes({
 *   @Attribute("column",     type = "string"),
 *   @Attribute("alias" ,     type = "string")
 * })
 */

class ColumnAlias extends AbstractAnnotation
{
    public $alias;
    public $column;

    public function __construct(array $data)
    {
        $this->alias  = $data["alias"]  ?? "";
        $this->column = $data["column"] ?? "";
    }

    public function supports(string $target, ?string $targetValue = null, $object = null):bool
    {
        return ($target == AnnotationReader::TARGET_CLASS || $target == AnnotationReader::TARGET_PROPERTY);
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target = null, ?string $targetValue = null)
    {
        if($target == "property") $alias = $targetValue;
        else $alias = $this->alias;

        if(!property_exists($classMetadata->getName(), $alias))
            throw new Exception("Invalid alias property \"$alias\" provided in annotation of class ".$classMetadata->getName());
        else if(!property_exists($classMetadata->getName(), $this->column))
            throw new Exception("Invalid column property \"$this->column\" provided in annotation of class ".$classMetadata->getName());
        else if($classMetadata->hasAssociation($alias))
            throw new Exception("Alias variable \"$alias\" cannot be used, association mapping already found.");
        else if($classMetadata->hasField($alias))
            throw new Exception("Alias variable \"$alias\" cannot be used, field mapping already found.");

        $classMetadataCompletor = $this->getClassMetadataCompletor($classMetadata);
        $classMetadataCompletor->aliasNames ??= [];
        $classMetadataCompletor->aliasNames[$alias] = $this->column;
    }

    public function bind($entity, $column, $alias)
    {
        $fn = function() use ($alias, $column) {

            $aliasValue  = $this->$alias;

            $columnValue = $this->$column;
            if($aliasValue instanceof ArrayCollection && $columnValue instanceof ArrayCollection)
                $aliasValue = new ArrayCollection($columnValue->toArray() + $aliasValue->toArray());
            else if($columnValue !== null)
                $aliasValue = $columnValue;

            $this->$alias = &$this->$column; // Bind variable together..
            $this->$alias = $aliasValue;

            return $this;
        };

        $fnClosure = \Closure::bind($fn, $entity, get_class($entity));
        $fnClosure();
    }

    protected static $i = 0;
    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $column = $this->column;
        $alias  = $property ?? $this->alias;

        $this->bind($entity, $column, $alias);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $column = $this->column;
        $alias  = $property ?? $this->alias;
        $this->bind($entity, $column, $alias);
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
        $column = $this->column;
        $alias  = $property ?? $this->alias;
        $this->bind($entity, $column, $alias);
    }
}
