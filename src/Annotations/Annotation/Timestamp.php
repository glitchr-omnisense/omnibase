<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use DateTime;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
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
 *   @Attribute("value", type = "datetime")
 * })
 */
class Timestamp extends AbstractAnnotation
{
    private array $fields;
    private array $context;

    /**
     * @var DateTime
     */
    private $value;

    public function __construct( array $data ) {

        $this->context = array_map("mb_strtolower", $data['on']);
        $this->fields = $data['fields'] ?? [];
        $this->value = $data['value'] ?? "";
    }

    public function getContext(): array {
        return $this->context;
    }

    public function getFields(): array {
        return $this->fields;
    }

    public function getValue(): \DateTime {

        if(!$this->value)
            $this->value = new DateTime("now");
            
        return $this->value;
    }

    public function supports($classMetadata, string $target, ?string $targetValue = null, $entity = null): bool
    {
        if(!empty($this->fields) && !in_array($targetValue, $this->fields)) return false;

        return in_array("update", $this->context) || in_array("create", $this->context);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if(!in_array("create", $this->context)) return;

        $this->setFieldValue($entity, $property, $this->getValue());
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if (!in_array("update", $this->context)) return;

        $this->setFieldValue($entity, $property, $this->getValue());
    }
}
