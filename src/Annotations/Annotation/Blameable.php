<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class Blameable
 * package Base\AnnotationReader\Abstract\Blameable
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"CLASS", "PROPERTY"})
 */

 #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class Blameable extends AbstractAnnotation
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
     * @param LifecycleEventArgs $event
     * @param ClassMetadata $classMetadata
     * @param $entity
     * @param string|null $property
     * @return void
     */
    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if (!in_array("create", $this->getContext())) {
            return;
        }

        $user = ($this->impersonator ? $this->getImpersonator() : $this->getUser());
        $this->setFieldValue($entity, $property, $user);
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
        if (!in_array("update", $this->getContext())) {
            return;
        }

        $user = ($this->impersonator ? $this->getImpersonator() : $this->getUser());
        $this->setFieldValue($entity, $property, $user);
    }
}
