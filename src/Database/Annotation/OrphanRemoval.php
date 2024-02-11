<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS", "PROPERTY"})
 * @Attributes({
 *   @Attribute("column" , type = "string"),
 *   @Attribute("value" , type = "bool")
 * })
 */

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class OrphanRemoval extends AbstractAnnotation
{
    /** @Required */
    protected string $column;
    protected bool $value;

    public function __construct(string $column = "", bool $value = true)
    {
        $this->column = $column;
        $this->value = $value;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
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
            throw new Exception("Invalid column property \"$column\" provided in annotation of class " . $classMetadata->getName());
        }

        $associationMapping = $classMetadata->getAssociationMapping($classMetadata->getFieldName($column));
        $associationMapping["orphanRemoval"] = boolval($this->value);

        if (array_key_exists($column, $classMetadata->associationMappings)) {
            $classMetadata->associationMappings[$column] = $associationMapping;
        }
    }
}
