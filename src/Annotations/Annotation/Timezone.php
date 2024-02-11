<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Class Timezone
 * package Base\Metadata\Extension\Timezone
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY"})
 */

 #[\Attribute(\Attribute::TARGET_PROPERTY)]
class Timezone extends AbstractAnnotation
{
    public const DEFAULT_TIMEZONE = "UTC";
    private array $context;

    /**
     * @var string
     */
    private string $value;

    public function __construct(string|array $on = [], string $value = "")
    {
        $this->context = array_map("mb_strtolower", is_string($on) ? [$on] : $on);
        $this->value = $value;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getValue(): string
    {
        $value = $this->value ?? date_default_timezone_get();
        if (!in_array($value, timezone_identifiers_list())) {
            $value = self::DEFAULT_TIMEZONE;
        }

        return $value;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return in_array("update", $this->context) || in_array("create", $this->context);
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
        if (!in_array("update", $this->context)) {
            return;
        }

        $this->setFieldValue($entity, $property, $this->getValue());
    }
}
