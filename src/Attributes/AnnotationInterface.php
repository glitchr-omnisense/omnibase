<?php

namespace Base\Attributes;

use Base\Database\Event\DoctrineQueryEventArgs;
use Base\Database\Event\ResolveDiscriminatorEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LifecycleEventArgs;

interface AnnotationInterface
{
    public function supports(string $target, ?string $targetValue = null, mixed $object = null): bool;

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null);
    
    public function resolveDiscriminator(ResolveDiscriminatorEventArgs $args) : void;

    public function preQuery(DoctrineQueryEventArgs $args) : void;

    public function onQuery(DoctrineQueryEventArgs $args) : void;

    public function postQuery(DoctrineQueryEventArgs $args) : void;

    public function preFlush(PreFlushEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);

    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);

    public function postFlush(PostFlushEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);

    public function preUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);

    public function preRemove(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);

    public function postPersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);

    public function postUpdate(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);

    public function postRemove(LifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null);
}
