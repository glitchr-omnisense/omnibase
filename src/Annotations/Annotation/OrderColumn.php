<?php

namespace Base\Annotations\Annotation;

use App\Entity\Blog\Comment;
use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Class OrderColumn
 * package Base\Annotations\Annotation\OrderColumn
 *
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("reference", type = "string"),
 * })
 */
class OrderColumn extends AbstractAnnotation
{
    protected string $referenceColumn;

    public function __construct( array $data ) {

        $this->referenceColumn = $data['reference'] ?? null;
    }

    public function getReferenceColumn()
    {
        return $this->referenceColumn;
    }

    public function supports($classMetadata, string $target, ?string $targetValue = null, $entity = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        // dump("PRE PERSIST !");
        // exit(1);
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        // dump("PRE UPDATE !");
        // exit(1);
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null)
    {
        // dump("LOAD CLASSMETADATA SORTING..". $target . " = ". $targetValue);
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        // dump("POST LOAD SORTING.. ".$property);
        // dump($entity);
    }

    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        // dump("FLUSH ORDER COLUMN.. ".$property);
        // dump($this->getOldEntity($entity));
        // dump($entity);
        
        exit(1);
    }
}
