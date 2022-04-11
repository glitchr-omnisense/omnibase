<?php

namespace Base\Database\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;

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
    private ?string $value;

    public function __construct( array $data ) 
    { 
        $this->value = $data['value'] ?? null;
    }

    public function getValue(object|string $object_or_class): string 
    {
        if($this->value === null) {

            // Formatting input object
            $className = is_object($object_or_class) ? get_class($object_or_class) : $object_or_class;
            $namespace = explodeByArray("\\Entity\\", $className);

            // Special case for App and Base entities
            switch (($namespaceRoot = array_shift($namespace))) {

                case "Base":

                    //NB: Either returning "common" or "abstract" value for root entity
                    $special = $namespaceRoot == "Base" ? (is_abstract($className) ? "abstract" : "common") : null;
                    if ($special && !get_parent_class($className)) return $special;
                    
                    // Otherwise.. Just put the class basename (as it is expected to be "general" terms.)
                    $namespace = array_unique(explode("\\", $namespace[0] ?? ""));
                    return mb_lcfirst(end($namespace));

                default : 
                case "App": 

                    $namespace = $namespace[0] ?? null;
                    if($namespace === null)
                        throw new Exception("Unexpected location for \"$className\"");
            
                    // Looking for custom parent values
                    $parentValue = null;
                    $parentNamespace = null;
                    if( $parentClassName = get_parent_class($className) ) {

                        $parentNamespace = explodeByArray("\\Entity\\", $parentClassName)[1] ?? null;
                        $parentAnnotations = $this->getAnnotationReader()->getAnnotations($parentClassName, $this);
                        $parentAnnotations = $parentAnnotations[AnnotationReader::TARGET_CLASS][$parentClassName];
                        if(($parentAnnotation  = $parentAnnotations ? end($parentAnnotations) : null)) {
                            $parentValue = $parentAnnotation->getValue($parentClassName);
                            $parentValue = in_array($parentValue, ["abstract", "common"]) ? null : $parentValue;
                        }
                    }

                    // Strip parent prefix namespace
                    if($parentNamespace !== null && $parentValue !== null && str_starts_with($namespace, $parentNamespace."\\")) {

                        $namespace = explode("\\", str_lstrip($namespace, $parentNamespace."\\"));
                        array_unshift($namespace, $parentValue);
                        
                    } else {

                        $namespace = explode("\\", $namespace);
                    }

                    // Return final entry value
                    $namespace = array_unique($namespace);
                    $namespace = array_map("mb_lcfirst", $namespace);

                    return implode("_", $namespace);
            }
        }


        return $this->value;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null):bool
    {
        if($object === null) return false;

        $classMetadata = null;
        if($object instanceof ClassMetadata)
            $classMetadata = $object;

        if($classMetadata === null) return false;
        if(empty($classMetadata->discriminatorMap) || empty($classMetadata->discriminatorColumn))
            throw new \Exception("@DiscriminatorEntry \"".$this->getValue($classMetadata->getName())."\" found for \"".$classMetadata->getName()."\", but missing discriminator mapping");

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
            $annotation = $annotations ? end($annotations) : null;
                if($annotation === null)
                    throw new \Exception("@DiscriminatorEntry missing for \"".$className."\".");

            $discriminatorValues[$className] = $annotation->getValue($className);
        }

        // Apply new discriminator
        $classMetadata->discriminatorMap   = array_flip($discriminatorValues);
        $classMetadata->discriminatorValue = $discriminatorValues[$classMetadata->getName()] ?? null;
        if($classMetadata->discriminatorValue === null)
            throw new \Exception("Missing discriminator entry in ".$classMetadata->getName());
    }
}
