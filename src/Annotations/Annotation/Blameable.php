<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class Blameable
 * package Base\Annotations\Annotation\Blameable
 *
 * @Annotation
 * @Target({"CLASS", "PROPERTY"})
 * @Attributes({
 *   @Attribute("on", type = "array"),
 *   @Attribute("fields", type = "array"),
 *   @Attribute("impersonator", type = "bool")
 * })
 */
class Blameable extends AbstractAnnotation
{
    private array $fields;
    private array $context;

    protected bool $impersonator;

    public function __construct(array $data)
    {
        $this->context = array_map("mb_strtolower", $data['on']);
        $this->fields = $data['fields'] ?? [];
        $this->impersonator = $data['impersonator'] ?? false;
    }

    public function getContext(): array
    {
        return $this->context;
    }
    public function getFields(): array
    {
        return $this->fields;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        if (!empty($this->fields) && !in_array($targetValue, $this->fields)) {
            return false;
        }

        return in_array("update", $this->getContext()) || in_array("create", $this->getContext());
    }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if (!in_array("create", $this->getContext())) {
            return;
        }

        $user = ($this->impersonator ? $this->getImpersonator() : $this->getUser());
        $this->setFieldValue($entity, $property, $user);
    }

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if (!in_array("update", $this->getContext())) {
            return;
        }

        $user = ($this->impersonator ? $this->getImpersonator() : $this->getUser());
        $this->setFieldValue($entity, $property, $user);
    }
}
