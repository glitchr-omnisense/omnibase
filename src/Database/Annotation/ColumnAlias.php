<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

/**
 * @Annotation
 * @Target({"CLASS", "PROPERTY"})
 * @Attributes({
 *   @Attribute("column",     type = "string"),
 *   @Attribute("inversedBy", type = "string"),
 *   @Attribute("mappedBy",   type = "string"),
 *   @Attribute("alias" ,     type = "string")
 * })
 */

class ColumnAlias extends AbstractAnnotation
{
    public function __construct(array $data)
    {
        $this->column = $data["column"] ?? "";
        $this->inversedBy = $data["inversedBy"] ?? "";
        $this->mappedBy = $data["mappedBy"] ?? "";
        $this->alias  = $data["alias"]  ?? "";
    }

    public function supports(string $target, ?string $targetValue = null, $object = null):bool
    {
        return ($target == AnnotationReader::TARGET_CLASS || $target == AnnotationReader::TARGET_PROPERTY);
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target = null, string $targetValue = null)
    {
        if($target == "property") $alias = $targetValue;
        else $alias = $this->alias;

        if(!property_exists($classMetadata->getName(), $alias))
            throw new Exception("Invalid alias property \"$alias\" provided in annotation of class ".$classMetadata->getName());
        else if(!property_exists($classMetadata->getName(), $this->column)) 
            throw new Exception("Invalid column property \"$this->column\" provided in annotation of class ".$classMetadata->getName());
        else if($classMetadata->hasAssociation($alias) /*&& !isset($classMetadata->associationMappings[$alias]["alias"])*/)
            throw new Exception("Alias variable \"$alias\" cannot be used, association mapping already found.");
        else if($classMetadata->hasField($alias) /*&& !isset($classMetadata->fieldMappings[$alias]["alias"])*/)
            throw new Exception("Alias variable \"$alias\" cannot be used, field mapping already found.");

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
