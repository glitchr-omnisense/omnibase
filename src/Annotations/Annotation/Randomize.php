<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class Randomize
 * package Base\Annotations\Annotation\Randomize
 *
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("length", type = "integer"),
 *   @Attribute("chars", type = "string"),
 * })
 */
class Randomize extends AbstractAnnotation
{
    protected ?int $length;
    protected ?string $chars;

    public function __construct(array $data)
    {
        $this->length = $data["length"] ?? null;
        $this->chars = $data['chars'] ?? null;
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
     * @param OnFlushEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     * @throws \Exception
     */
    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if ($this->getFieldValue($entity, $property) === null) {
            $this->setFieldValue($entity, $property, rand_str($this->length, $this->chars));

            if ($this->getUnitOfWork()->getEntityChangeSet($entity)) {
                $this->getUnitOfWork()->recomputeSingleEntityChangeSet($classMetadata, $entity);
            }
        }
    }
}
