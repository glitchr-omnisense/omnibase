<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\ORM\Mapping\ClassMetadata;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Class Hierarchify
 * package Base\Annotations\Annotation\Hierarchify.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS"})
 */

 #[\Attribute(\Attribute::TARGET_CLASS)]
class Hierarchify extends AbstractAnnotation
{
    /**
     * @var array|string|null
     */
    public array|string|null $hierarchy;

    /**
     * @var string|null
     */
    public ?string $separator;

    public function __construct(string|array $hierarchy = null, ?string $separator = null)
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
        return AnnotationReader::TARGET_CLASS == $target;
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target = null, ?string $targetValue = null)
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
