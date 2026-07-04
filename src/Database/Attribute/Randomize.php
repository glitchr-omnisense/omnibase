<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAnnotation;
use Base\Attributes\AnnotationReader;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;


/**
 * Class Randomize
 * package Base\Database\Attribute\Randomize
 */

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Randomize extends AbstractAnnotation implements ExtensionOptionInterface
{
    protected ?int $length;
    protected ?string $chars;

    public function __construct(?int $length = null, ?string $chars = null)
    {
        $this->length = $length;
        $this->chars = $chars;
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
