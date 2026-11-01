<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Base\Database\Entity\EntityExtension;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

use Omnibridge\Persistence\Lifecycle;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class Alias extends AbstractAttribute implements ExtensionOptionInterface
{
    public string $alias;
    public string $column;
    public ?string $type;

    public function __construct(string $column = "", string $alias = "", ?string $type = null)
    {
        $this->column = $column;
        $this->alias = $alias;
        $this->type = $type; // filter by class if provided (for associations)
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
            $alias = $targetValue;
        } else {
            $alias = $this->alias;
        }

        if (!property_exists($classMetadata->getName(), $alias)) {
            throw new Exception("Invalid alias property \"$alias\" provided in metadata of class " . $classMetadata->getName());
        } elseif (!property_exists($classMetadata->getName(), $this->column)) {
            throw new Exception("Invalid column property \"$this->column\" provided in metadata of class " . $classMetadata->getName());
        } elseif ($classMetadata->hasAssociation($alias)) {
            throw new Exception("Alias variable \"$alias\" cannot be used, association mapping already found.");
        } elseif ($classMetadata->hasField($alias)) {
            throw new Exception("Alias variable \"$alias\" cannot be used, field mapping already found.");
        }

        $classMetadataCompletor = $this->getClassMetadataCompletor($classMetadata);
        $classMetadataCompletor->aliasNames ??= [];
        $classMetadataCompletor->aliasNames[$alias] = $this->column;
    }

    /**
     * @param $entity
     * @param $column
     * @param $alias
     * @return void
     */
    public function bind($entity, $column, $alias)
    {
        if ($alias == $column) {
            return;
        }
        if (snake2camel($alias) == snake2camel($column)) {
            return;
        }
        if (camel2snake($alias) == camel2snake($column)) {
            return;
        }

        $fn = function () use ($alias, $column) {
            $aliasValue = $this->$alias;

            $columnValue = $this->$column;
            if ($aliasValue instanceof ArrayCollection && $columnValue instanceof ArrayCollection) {
                $aliasValue = new ArrayCollection($columnValue->toArray() + $aliasValue->toArray());
            } elseif ($columnValue !== null) {
                $aliasValue = $columnValue;
            }

            $this->$alias = &$this->$column; // Bind variable together..
            $this->$alias = $aliasValue;

            return $this;
        };

        $fnClosure = Closure::bind($fn, $entity, get_class($entity));
        $fnClosure();
    }

    protected static int $i = 0;

    /** The neutral hooks (plan "omnibase agnostic", step F2c): the alias bound to its column. */
    public function onPostLoad(Lifecycle\PostLoad $event, ?string $property = null): void
    {
        $this->bind($event->entity, $this->column, $property ?? $this->alias);
    }

    public function onPrePersist(Lifecycle\PrePersist $event, ?string $property = null): void
    {
        $this->bind($event->entity, $this->column, $property ?? $this->alias);
    }

    public function onPreUpdate(Lifecycle\PreUpdate $event, ?string $property = null): void
    {
        $this->bind($event->entity, $this->column, $property ?? $this->alias);
    }
}
