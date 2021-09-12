<?php

namespace Base\Database\Annotation;

use App\Entity\Gallery\Gallery;
use Base\Database\AbstractAnnotation;
use Base\Database\AnnotationReader;
use App\Entity\Thread;
use Base\Service\BaseService;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

/**
 * Class EntityHierarchy
 * package Base\Database\Annotation\EntityHierarchy
 *
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("hierarchy", type = "array"),
 *   @Attribute("separator", type = "string")
 * })
 */

class EntityHierarchy extends AbstractAnnotation
{
    public function __construct(array $data)
    {
        $this->hierarchy = $data["hierarchy"] ?? null;
        $this->separator = $data["separator"]  ?? null;
    }

    public function supports(ClassMetadata $classMetadata, string $target, ?string $targetValue = null, $entity = null):bool
    {
        return ($target == AnnotationReader::TARGET_CLASS);
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target = null, string $targetValue = null)
    {
        if(!method_exists($classMetadata->customRepositoryClassName, "getHierarchyTree") && !$this->parent_method_exists($classMetadata->customRepositoryClassName, "getHierarchyTree"))
            throw new Exception("Did you forgot to use Base\Repository\Trait\EntityHierarchyTrait in $classMetadata->customRepositoryClassName ?");

        $classMetadata->entityHierarchy = $this->hierarchy;
        $classMetadata->entityHierarchySeparator = $this->separator;
    }

    function parent_method_exists($object,$method) {

        foreach(class_parents($object) as $parent)
            if(method_exists($parent,$method)) return true;

        return false;
    }
}