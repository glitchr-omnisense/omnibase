<?php

namespace Base\Database\Annotation;

use Base\Database\AbstractAnnotation;
use Base\Database\AnnotationReader;
use DateTime;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class Timezone
 * package Base\Database\Annotation\Timezone
 *
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("on", type = "array"),
 * })
 */
class Timezone extends AbstractAnnotation
{
    public const DEFAULT_TIMEZONE = "UTC";
    private array $context;

    /**
     * @var DateTime
     */
    private string $value;

    public function __construct(array $data)
    {
        $this->context = array_map("strtolower", $data['on']);
        $this->value = $data['value'] ?? "";
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getValue(): string
    {
        $value = $this->value ?? date_default_timezone_get();
        if (!in_array($value, timezone_identifiers_list()))
            $value = self::DEFAULT_TIMEZONE;

        return $value;
    }

    public function supports($classMetadata, string $target, ?string $targetValue = null, $entity = null): bool
    {
        return in_array("update", $this->context) || in_array("create", $this->context);
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if (!in_array("create", $this->context)) return;
        $this->setFieldValue($entity, $property, $this->getValue());
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if (!in_array("update", $this->context)) return;

        $this->setFieldValue($entity, $property, $this->getValue());
    }
}
