<?php

namespace Base\Database;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

interface AnnotationInterface
{
             public function supports(ClassMetadata $classMetadata, string $target, ?string $targetValue = null, $entity = null): bool;
    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null);

    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null);

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null);
    public function preUpdate (LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null);
    public function preRemove (LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null);

    public function postPersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null);
    public function postUpdate (LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null);
    public function postRemove (LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null);

}