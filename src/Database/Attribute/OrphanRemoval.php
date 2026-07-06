<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Database\Entity\EntityExtension;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;


/**
 * @Attributes({
 *   @Attribute("column" , type = "string"),
 *   @Attribute("value" , type = "bool")
 * })
 */

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class OrphanRemoval extends AbstractAttribute implements ExtensionOptionInterface
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
        return ($target == EntityExtension::TARGET_CLASS || $target == EntityExtension::TARGET_PROPERTY);
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
        if ($target == "property") {
            $column = $targetValue;
        } else {
            $column = $this->column;
        }

        $columnAlias = $this->getAttribute($classMetadata, $column, Alias::class);
        if ($columnAlias) {
            $column = $columnAlias->column;
        }

        if (!property_exists($classMetadata->getName(), $column)) {
            throw new Exception("Invalid column property \"$column\" provided in attribute of class " . $classMetadata->getName());
        }

        $associationMapping = $classMetadata->getAssociationMapping($classMetadata->getFieldName($column));
        $associationMapping["orphanRemoval"] = boolval($this->value);

        if (array_key_exists($column, $classMetadata->associationMappings)) {
            $classMetadata->associationMappings[$column] = $associationMapping;
        }
    }
}
