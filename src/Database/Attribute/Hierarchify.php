<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Mapping\Completor;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Doctrine\ORM\Mapping\ClassMetadata;


/**
 * Class Hierarchify
 * package Base\Database\Attribute\Hierarchify.
 */

 #[\Attribute(\Attribute::TARGET_CLASS)]
class Hierarchify extends AbstractAttribute implements ExtensionOptionInterface 
{
    /**
     * @var array|string|null
     */
    public array|string $hierarchy;

    /**
     * @var string|null
     */
    public ?string $separator;

    public function __construct(string|array|null $hierarchy = null, ?string $separator = "/")
    {
        // Its levels, from the first: one ("articles") or several (["articles", "comments"], which were dropped).
        $this->hierarchy = is_string($hierarchy) ? [$hierarchy] : array_values($hierarchy ?? []);
        $this->separator = $separator ?? null;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return AttributeReader::TARGET_CLASS == $target;
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
        if (!method_exists($classMetadata->customRepositoryClassName, 'getHierarchyTree') && !$this->parent_method_exists($classMetadata->customRepositoryClassName, 'getHierarchyTree')) {
            throw new \Exception("Did you forgot to use \"Base\Metadata\Traits\HierarchifyTrait\" in $classMetadata->customRepositoryClassName ?");
        }
    }

    /** The hierarchy and its separator, on the class (plan "omnibase agnostic", step E3). */
    public function complete(Completor $completor, string $target, ?string $targetValue = null): void
    {
        if (AttributeReader::TARGET_CLASS !== $target) {
            return;
        }

        $completor->entityHierarchy ??= $this->hierarchy;
        $completor->entityHierarchySeparator ??= $this->separator;
    }

    /**
     * @param $object
     * @param $method
     * @return bool
     */
    public function parent_method_exists($object, $method)
    {
        foreach (class_parents($object) as $parent) {
            if (method_exists($parent, $method)) {
                return true;
            }
        }

        return false;
    }
}
