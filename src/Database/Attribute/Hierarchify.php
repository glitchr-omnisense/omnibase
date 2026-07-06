<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
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
        $this->hierarchy = is_string($hierarchy) ? [$hierarchy] : [];
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

        $classMetadataCompletor = $this->getClassMetadataCompletor($classMetadata);
        $classMetadataCompletor->entityHierarchy ??= $this->hierarchy;
        $classMetadataCompletor->entityHierarchySeparator ??= $this->separator;
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
