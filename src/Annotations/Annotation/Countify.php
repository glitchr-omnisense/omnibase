<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Exception;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY"})
 */

 #[\Attribute(\Attribute::TARGET_PROPERTY)]
class Countify extends AbstractAnnotation
{
    protected ?string $referenceColumn;
    protected string $type;

    public const COUNT_CHARS = "chars";
    public const COUNT_LETTERS = "letters";
    public const COUNT_WORDS = "words";
    public const COUNT_SENTENCES = "sentences";
    public const COUNT_BLOCKS = "blocks";

    public function __construct(?string $reference = null, string $type = "")
    {
        $this->referenceColumn = $reference;

        switch ($type) {
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

    /**
     * @return mixed|string|null
     */
    public function getReferenceColumn()
    {
        return $this->referenceColumn;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    /**
     * @param $entity
     * @return int
     * @throws Exception
     */
    public function getCount($entity): int
    {
        if (!$this->referenceColumn) {
            throw new Exception("Attribute \"reference\" missing for @Countify in " . get_class($entity));
        }

        // Check if field already set.. (it needs to be checked)
        $value = $this->getFieldValue($entity, $this->referenceColumn) ?? "";
        switch ($this->type) {
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

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     * @throws Exception
     */
    public function postLoad(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $nWords = $this->getFieldValue($entity, $property);
        if ($nWords) {
            return;
        }

        $count = $this->getCount($entity) ?? 0;
        $this->setFieldValue($entity, $property, $count);
    }

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     * @throws Exception
     */
    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $count = $this->getCount($entity) ?? 0;
        $this->setFieldValue($entity, $property, $count);
    }

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     * @throws Exception
     */
    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        $count = $this->getCount($entity) ?? 0;
        $this->setFieldValue($entity, $property, $count);
    }
}
