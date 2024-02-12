<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Class Timestamp
 * package Base\Annotations\Annotation\Timestamp
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS", "PROPERTY"})
 */

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class Timestamp extends AbstractAnnotation
{
    private array $fields;
    private array $context;

    private bool $immutable;

    /**
     * @var DateTime
     */
    private $value;

    public function __construct(string|array $on = [], bool $immutable = false, array $fields = [], string $value = "")
    {
        $this->context = array_map("mb_strtolower", is_string($on) ? [$on] : $on);
        $this->immutable = $immutable;
        $this->fields = $fields;
        $this->value = $value;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function isImmutable(): bool
    {
        return $this->immutable;
    }

    public function getValue(): DateTime
    {
        if (!$this->value) {
            $this->value = $this->isImmutable() ? new DateTimeImmutable("now") : new DateTime("now");
        }

        return $this->value;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        if (!empty($this->fields) && !in_array($targetValue, $this->fields)) {
            return false;
        }

        return in_array($this->getImpersonator() ? "impersonator" : "update", $this->context) || in_array("create", $this->context);
    }

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     */
    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if (!in_array("create", $this->context)) {
            return;
        }

        $this->setFieldValue($entity, $property, $this->getValue());
    }

    /**
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     */
    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if (!in_array($this->getImpersonator() ? "impersonator" : "update", $this->context)) {
            return;
        }

        $this->setFieldValue($entity, $property, $this->getValue());
    }
}
