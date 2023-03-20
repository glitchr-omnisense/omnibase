<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

/**
 * Class Hierarchify
 * package Base\Annotations\Annotation\Hierarchify
 *
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("hierarchy", type = "array"),
 *   @Attribute("separator", type = "string")
 * })
 */

class Hierarchify extends AbstractAnnotation
{
    /**
     * @var array
     */
    public ?array $hierarchy;

    /**
     * @var string
     */
    public ?string $separator;

    public function __construct(array $data)
    {
        $this->hierarchy = $data["hierarchy"] ?? null;
        $this->separator = $data["separator"]  ?? null;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_CLASS);
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target = null, ?string $targetValue = null)
    {
        if (!method_exists($classMetadata->customRepositoryClassName, "getHierarchyTree") && !$this->parent_method_exists($classMetadata->customRepositoryClassName, "getHierarchyTree")) {
            throw new Exception("Did you forgot to use \"Base\Annotations\Traits\HierarchifyTrait\" in $classMetadata->customRepositoryClassName ?");
        }

        $classMetadataCompletor = $this->getClassMetadataCompletor($classMetadata);
        $classMetadataCompletor->entityHierarchy ??= $this->hierarchy;
        $classMetadataCompletor->entityHierarchySeparator ??= $this->separator;
    }

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
