<?php

namespace Base\Database\Attribute;

use Omnibridge\Persistence\Lifecycle;
use Base\Attributes\AbstractAttribute;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;


/**
 * Class Timezone
 * package Base\Database\Attribute\Timezone
 */

 #[\Attribute(\Attribute::TARGET_PROPERTY)]
class Timezone extends AbstractAttribute implements ExtensionOptionInterface
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
     * The neutral hook (plan "omnibase agnostic", step F2b): the entity and its unit of work.
     * @param string|null $property
     */
    public function onPrePersist(Lifecycle\PrePersist $event, ?string $property = null): void
    {
        $entity = $event->entity;
        if (!in_array("create", $this->context)) {
            return;
        }
        $this->setFieldValue($entity, $property, $this->getValue());
    }

    /**
     * The neutral hook (plan "omnibase agnostic", step F2b): the entity and its unit of work.
     * @param string|null $property
     */
    public function onPreUpdate(Lifecycle\PreUpdate $event, ?string $property = null): void
    {
        $entity = $event->entity;
        if (!in_array("update", $this->context)) {
            return;
        }

        $this->setFieldValue($entity, $property, $this->getValue());
    }
}
