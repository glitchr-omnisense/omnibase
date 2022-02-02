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
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("reference", type = "string"),
 *   @Attribute("type",      type = "string"),
 *   @Attribute("updatable", type = "bool"),
 *   @Attribute("unique",    type = "bool"),
 *
 *   @Attribute("locale",    type = "string"),
 *   @Attribute("map",       type = "array"),
 *   @Attribute("separator", type = "string"),
 *   @Attribute("lowercase", type = "bool")
 * })
 */
class Countify extends AbstractAnnotation
{
    protected string $reference;

    public const COUNT_CHARS     = 0;
    public const COUNT_LETTERS   = 1;
    public const COUNT_WORDS     = 1;
    public const COUNT_SENTENCES = 2;
    public const COUNT_BLOCKS    = 3;
    public function __construct( array $data )
    {
        $this->referenceColumn = $data['reference'] ?? null;
        
        switch($data["type"]) 
        {
            default:
            case self::COUNT_CHARS:
                $this->type = self::COUNT_CHARS;
                break;

            case self::COUNT_WORDS:
                $this->type = self::COUNT_WORDS;
                break;

            case self::COUNT_LETTERS:
                $this->type = self::COUNT_LETTERS;
                break;

            case self::COUNT_SENTENCES:
                $this->type = self::COUNT_SENTENCES;
                break;

            case self::COUNT_BLOCKS:
                $this->type = self::COUNT_BLOCKS;
                break;
        }
    }

    public function getReferenceColumn()
    {
        return $this->referenceColumn;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function getCount($entity): string
    {
        if (!$this->referenceColumn)
            throw new Exception("Attribute \"reference\" missing for @Countify in " . get_class($entity));

        // Check if field already set.. (it needs to be checked)
        $value = $this->getPropertyValue($entity, $this->referenceColumn) ?? "";
        
        switch($this->type) 
        {
            default:
            case self::COUNT_CHARS:
                return strlen(strip_tags($value));

            case self::COUNT_LETTERS:
                throw new Exception("Letter counting not implemented yet");
                break;

            case self::COUNT_WORDS:
                return str_word_count(strip_tags($value));

            case self::COUNT_SENTENCES:
                throw new Exception("Sentence counting not implemented yet");
                break;

            case self::COUNT_BLOCKS:
                throw new Exception("Block counting not implemented yet");
                break;
        }

        return str_word_count(strip_tags($value));
    }

    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $nWords = $this->getPropertyValue($entity, $property);
        if($nWords) return;

        $slug = $this->getCount($entity);
        $this->setPropertyValue($entity, $property, $slug);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $slug = $this->getCount($entity);
        $this->setPropertyValue($entity, $property, $slug);
    }
    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $slug = $this->getCount($entity);
        $this->setPropertyValue($entity, $property, $slug);
    }
}
