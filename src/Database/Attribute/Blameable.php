<?php

namespace Base\Database\Attribute;

use Omnibridge\Persistence\Lifecycle;
use Base\Attributes\AbstractAttribute;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class Blameable
 * package Base\AttributeReader\Abstract\Blameable
 */

 #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class Blameable extends AbstractAttribute implements ExtensionOptionInterface
{
    private array $fields;
    private array $context;

    protected bool $impersonator;

    public function __construct(string|array $on = [], array $fields = [], bool $impersonator = false)
    {
        $this->context = array_map("mb_strtolower", is_string($on) ? [$on] : $on);
        $this->fields = $fields;
        $this->impersonator = $impersonator;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getFields(): array
    {
        return $this->fields;
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

        return in_array("update", $this->getContext()) || in_array("create", $this->getContext());
    }

    /**
     * The neutral hook (plan "omnibase agnostic", step F2b): the entity and its unit of work.
     * @param string|null $property
     */
    public function onPrePersist(Lifecycle\PrePersist $event, ?string $property = null): void
    {
        $entity = $event->entity;
        if (!in_array("create", $this->getContext())) {
            return;
        }

        $user = ($this->impersonator ? $this->getImpersonator() : $this->getUser());
        $this->setFieldValue($entity, $property, $user, $event->unitOfWork);
    }

    /**
     * The neutral hook (plan "omnibase agnostic", step F2b): the entity and its unit of work.
     * @param string|null $property
     */
    public function onPreUpdate(Lifecycle\PreUpdate $event, ?string $property = null): void
    {
        $entity = $event->entity;
        if (!in_array("update", $this->getContext())) {
            return;
        }

        $user = ($this->impersonator ? $this->getImpersonator() : $this->getUser());
        $this->setFieldValue($entity, $property, $user, $event->unitOfWork);
    }
}
