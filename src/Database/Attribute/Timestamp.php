<?php

namespace Base\Database\Attribute;

use Omnibridge\Persistence\Lifecycle;
use Base\Attributes\AbstractAttribute;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use DateTime;
use DateTimeImmutable;
use Base\Database\Type\Utc;


/**
 * Class Timestamp
 * package Base\Database\Attribute\Timestamp
 */

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class Timestamp extends AbstractAttribute implements ExtensionOptionInterface
{
    private array $fields;
    private array $context;

    private bool $immutable;

    /**
     * @var DateTime|DateTimeImmutable|string the moment, memoised; "" until it is asked
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

    public function getValue(): DateTime|DateTimeImmutable
    {
        if (!$this->value instanceof \DateTimeInterface) {
            $this->value = $this->isImmutable() ? Utc::now() : DateTime::createFromImmutable(Utc::now()); // the application's clock, in UTC
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
     * The neutral hook (plan "omnibase agnostic", step F2b): the entity and its unit of work.
     * @param string|null $property
     */
    public function onPrePersist(Lifecycle\PrePersist $event, ?string $property = null): void
    {
        $entity = $event->entity;
        if (!in_array("create", $this->context)) {
            return;
        }

        $this->setFieldValue($entity, $property, $this->getValue(), $event->unitOfWork);
    }

    /**
     * The neutral hook (plan "omnibase agnostic", step F2b): the entity and its unit of work.
     * @param string|null $property
     */
    public function onPreUpdate(Lifecycle\PreUpdate $event, ?string $property = null): void
    {
        $entity = $event->entity;
        if (!in_array($this->getImpersonator() ? "impersonator" : "update", $this->context)) {
            return;
        }

        $this->setFieldValue($entity, $property, $this->getValue(), $event->unitOfWork);
    }
}
