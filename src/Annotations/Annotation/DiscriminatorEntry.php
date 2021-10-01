<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Base\Database\TranslatableInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class DiscriminatorEntry
 * package Base\Annotations\Annotation\DiscriminatorEntry
 *
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("value", type = "string")
 * })
 */

class DiscriminatorEntry extends AbstractAnnotation
{
    /** @Required */
    private string $value;

    public function __construct( array $data ) {

        $this->value = $data['value'];
    }

    public function getValue(): string {
        return $this->value;
    }

    public function supports(ClassMetadata $classMetadata, string $target, ?string $targetValue = null, $entity = null):bool
    {
        // Only act on the top parent class when discriminatorMap found
        return empty($classMetadata->parentClasses) && count($classMetadata->discriminatorMap) > 0;
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target = null, string $targetValue = null)
    {
        // Recompute the map discriminator
        $discriminatorValues = [];
        foreach ($classMetadata->discriminatorMap as $className) {

            $annotations = $this->getAnnotationReader()->getAnnotations($className, $this);
            $annotations = $annotations[AnnotationReader::TARGET_CLASS][$className];

            foreach ($annotations as $annotation)
                $discriminatorValues[$className] = $annotation->getValue();
        }

        // Apply new discriminator
        $classMetadata->discriminatorMap   = array_flip($discriminatorValues);
        $classMetadata->discriminatorValue = $discriminatorValues[$classMetadata->getName()] ?? null;
        if($classMetadata->discriminatorValue === null)
            throw new \Exception("Missing discriminator entry in ".$classMetadata->getName());
    }
}
