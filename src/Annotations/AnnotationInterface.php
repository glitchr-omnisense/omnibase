<?php

namespace Base\Annotations;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

interface AnnotationInterface
{
    public function supports(string $target, ?string $targetValue = null, mixed $object = null): bool;
    public function postParser(mixed $object = null);

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null);

    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);
    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);
    public function preRemove(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);

    public function postPersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);
    public function postUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);
    public function postRemove(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);
}
