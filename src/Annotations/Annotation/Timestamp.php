<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class Timestamp
 * package Base\Annotations\Annotation\Timestamp
 *
 * @Annotation
 * @Target({"CLASS", "PROPERTY"})
 * @Attributes({
 *   @Attribute("on", type = "array"),
 *   @Attribute("fields", type = "array"),
 *   @Attribute("value", type = "datetime"),
 *   @Attribute("immutable", type = "bool")
 * })
 */
class Timestamp extends AbstractAnnotation
{
    private array $fields;
    private array $context;
    
    private bool $immutable;

    /**
     * @var DateTime
     */
    private $value;

    public function __construct( array $data )
    {
        $this->context   = array_map("mb_strtolower", $data['on']);
        $this->immutable = $data['immutable'] ?? false;
        $this->fields    = $data['fields'] ?? [];
        $this->value     = $data['value'] ?? "";
    }

    public function getContext(): array {
        return $this->context;
    }

    public function getFields(): array {
        return $this->fields;
    }

    public function isImmutable(): bool {
        return $this->immutable;
    }

    public function getValue(): \DateTime {

        if(!$this->value)
            $this->value = $this->isImmutable() ? new DateTimeImmutable("now") : new DateTime("now");

        return $this->value;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        if(!empty($this->fields) && !in_array($targetValue, $this->fields)) return false;

        return in_array($this->getImpersonator() ? "impersonator" : "update", $this->context) || in_array("create", $this->context);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if(!in_array("create", $this->context)) return;

        $this->setFieldValue($entity, $property, $this->getValue());
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if (!in_array($this->getImpersonator() ? "impersonator" : "update", $this->context)) return;

        $this->setFieldValue($entity, $property, $this->getValue());
    }
}
