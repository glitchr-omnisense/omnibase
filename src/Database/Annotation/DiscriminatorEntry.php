<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
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

    public function __construct( array $data ) 
    { 
        $this->value = $data['value'];
    }

    public function getValue(): string { return $this->value; }

    public function supports(string $target, ?string $targetValue = null, $object = null):bool
    {
        if($object === null) return false;

        $classMetadata = null;
        if($object instanceof ClassMetadata)
            $classMetadata = $object;

        if($classMetadata === null) return false;
        if(empty($classMetadata->discriminatorMap) || empty($classMetadata->discriminatorColumn))
            throw new \Exception("@DiscriminatorEntry \"".$this->value."\" found for \"".$classMetadata->getName()."\", but missing discriminator mapping");

        // Only act on the top parent class when discriminatorMap found
        return empty($classMetadata->parentClasses) && count($classMetadata->discriminatorMap) > 0;
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target = null, string $targetValue = null)
    {
        // Recompute the map discriminator
        $discriminatorValues = [];
        foreach ($classMetadata->discriminatorMap as $className) {

            $annotations = $this->getAnnotationReader()->getAnnotationsFor($className, $this);

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
