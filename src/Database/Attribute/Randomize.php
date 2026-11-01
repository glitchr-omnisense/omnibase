<?php

namespace Base\Database\Attribute;

use Omnibridge\Persistence\Lifecycle;
use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;


/**
 * Class Randomize
 * package Base\Database\Attribute\Randomize
 */

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Randomize extends AbstractAttribute implements ExtensionOptionInterface
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
        return ($target == AttributeReader::TARGET_PROPERTY);
    }

    /**
     * The neutral hook (plan "omnibase agnostic", step F2b): the flush's unit of work.
     * @param string|null $property
     * @return void
     * @throws \Exception
     */
    public function onOnFlush(Lifecycle\OnFlush $event, object $entity, ?string $property = null): void
    {
        if ($this->getFieldValue($entity, $property) === null) {
            $this->setFieldValue($entity, $property, rand_str($this->length, $this->chars));

            if ($event->unitOfWork->changes($entity)) {
                $event->unitOfWork->recompute($entity);
            }
        }
    }
}
