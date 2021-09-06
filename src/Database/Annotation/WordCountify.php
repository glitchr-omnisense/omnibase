<?php

namespace Base\Database\Annotation;

use App\Entity\Blog\Comment;
use Base\Database\AbstractAnnotation;
use Base\Database\AnnotationReader;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Class WordCountify
 * package Base\Database\Annotation\WordCountify
 *
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("reference", type = "string"),
 *   @Attribute("updatable", type = "bool"),
 *   @Attribute("unique",    type = "bool"),
 *
 *   @Attribute("locale",    type = "string"),
 *   @Attribute("map",       type = "array"),
 *   @Attribute("separator", type = "string"),
 *   @Attribute("lowercase", type = "bool")
 * })
 */
class WordCountify extends AbstractAnnotation
{
    protected string $reference;

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

    public function getWordCount($entity): string
    {
        if (!$this->referenceColumn)
            throw new Exception("Attribute \"reference\" missing for @WordCountify in " . get_class($entity));

        // Check if field already set.. (it needs to be checked)
        $value = $this->getFieldValue($entity, $this->referenceColumn) ?? "";
        return str_word_count(strip_tags($value));
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $nWords = $this->getFieldValue($entity, $property);
        if($nWords) return;

        $slug = $this->getWordCount($entity);
        $this->setFieldValue($entity, $property, $slug);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $slug = $this->getWordCount($entity);
        $this->setFieldValue($entity, $property, $slug);
    }
    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $slug = $this->getWordCount($entity);
        $this->setFieldValue($entity, $property, $slug);
    }
}
