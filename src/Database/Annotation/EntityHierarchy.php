<?php

namespace Base\Database\Annotation;

use Base\Database\AbstractAnnotation;
use Base\Database\AnnotationReader;
use App\Entity\Thread;
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
        $reflClass = new \ReflectionClass($targetValue);
        if(!$reflClass->hasProperty("entityHierarchy"))
            throw new Exception("Did you forgot to use Base\Trait\EntityHierarchyTrait in $targetValue ?");

        if($this->hierarchy) $reflClass->setStaticPropertyValue("entityHierarchy",          $this->hierarchy);
        if($this->separator) $reflClass->setStaticPropertyValue("entityHierarchySeparator", $this->separator);
    }
}