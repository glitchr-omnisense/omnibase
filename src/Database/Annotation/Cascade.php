<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

/**
 * @Annotation
 * @Target({"CLASS", "PROPERTY"})
 * @Attributes({
 *   @Attribute("column" , type = "string"),
 *   @Attribute("value" , type = "array")
 * })
 */

class Cascade extends AbstractAnnotation
{
    /** @Required */
    private string $column;
    private array $value;

    public function __construct(array $data)
    {
        $this->column = $data["column"]  ?? "";
        $this->value  = $data["value"]  ?? "";
    }

    public function supports(string $target, ?string $targetValue = null, $entity = null): bool
    {
        return ($target == AnnotationReader::TARGET_CLASS || $target == AnnotationReader::TARGET_PROPERTY);
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target = null, string $targetValue = null)
    {
        if ($target == "property") {
            $column = $targetValue;
        } else {
            $column = $this->column;
        }

        $columnAlias = $this->getAnnotation($classMetadata, $column, ColumnAlias::class);
        if ($columnAlias) {
            $column = $columnAlias->column;
        }

        if (!property_exists($classMetadata->getName(), $column)) {
            throw new Exception("Invalid column property \"$column\" provided in annotation of class ".$classMetadata->getName());
        }

        $associationMapping = $classMetadata->getAssociationMapping($column);
        $associationMapping["cascade"]          = $this->value;
        $associationMapping["isCascadeRemove"]  = in_array("remove", $associationMapping["cascade"]);
        $associationMapping["isCascadePersist"] = in_array("persist", $associationMapping["cascade"]);
        $associationMapping["isCascadeRefresh"] = in_array("refresh", $associationMapping["cascade"]);
        $associationMapping["isCascadeMerge"]   = in_array("merge", $associationMapping["cascade"]);
        $associationMapping["isCascadeDetach"]  = in_array("detach", $associationMapping["cascade"]);

        if (array_key_exists($column, $classMetadata->associationMappings)) {
            $classMetadata->associationMappings[$column] = $associationMapping;
        }
    }
}
