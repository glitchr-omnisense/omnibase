<?php

namespace Base\DatabaseSubscriber;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\BaseBundle;
use Base\Database\Event\DoctrineQueryEventArgs;
use Base\Database\Event\ResolveDiscriminatorEventArgs;
use Base\Database\Mapping\ClassMetadataManipulator;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;

class AttributeSubscriber
{
    /**
     * @var AttributeReader
     */
    protected AttributeReader $attributeReader;

    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;

    public function __construct(EntityManagerInterface $entityManager, ClassMetadataManipulator $classMetadataManipulator, AttributeReader $attributeReader)
    {
        $this->entityManager = $entityManager;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->attributeReader = $attributeReader;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event)
    {
        $className = $event->getClassMetadata()->name;
        $classMetadata = $event->getClassMetadata();

        $attributes = $this->attributeReader->getAttributes($className);

        $classAttributes = $attributes[AttributeReader::TARGET_CLASS][$className] ?? [];
        foreach ($classAttributes as $attribute) {
            if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                continue;
            }

            if (!in_array(AttributeReader::TARGET_CLASS, $this->attributeReader->getAttributeTargets($attribute))) {
                continue;
            }

            if (!$attribute->supports(AttributeReader::TARGET_CLASS, $className, $classMetadata)) {
                continue;
            }

            $attribute->loadClassMetadata($classMetadata, AttributeReader::TARGET_CLASS, $className);
        }

        $methodAttributes = $attributes[AttributeReader::TARGET_METHOD][$className] ?? [];
        foreach ($methodAttributes as $method => $_) {
            foreach ($_ as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }

                if (!in_array(AttributeReader::TARGET_METHOD, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }

                if (!$attribute->supports(AttributeReader::TARGET_METHOD, $method, $classMetadata)) {
                    continue;
                }

                $attribute->loadClassMetadata($classMetadata, AttributeReader::TARGET_METHOD, $method);
            }
        }

        $propertyAttributes = $attributes[AttributeReader::TARGET_PROPERTY][$className] ?? [];
        foreach ($propertyAttributes as $property => $_) {
            foreach ($_ as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }

                if (!in_array(AttributeReader::TARGET_PROPERTY, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }

                if (!$attribute->supports(AttributeReader::TARGET_PROPERTY, $property, $classMetadata)) {
                    continue;
                }

                $attribute->loadClassMetadata($classMetadata, AttributeReader::TARGET_PROPERTY, $property);
            }
        }

        $this->classMetadataManipulator->saveCompletors();
        $this->classMetadataManipulator->commitCache();
    }

    public function resolveDiscriminator(ResolveDiscriminatorEventArgs $event)
    {
        $className = $event->getClassMetadata()->name;
        $classMetadata = $event->getClassMetadata();

        $attributes = $this->attributeReader->getAttributes($className);

        $classAttributes = $attributes[AttributeReader::TARGET_CLASS][$className] ?? [];
        foreach ($classAttributes as $attribute) {
            if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                continue;
            }

            if (!in_array(AttributeReader::TARGET_CLASS, $this->attributeReader->getAttributeTargets($attribute))) {
                continue;
            }

            if (!$attribute->supports(AttributeReader::TARGET_CLASS, $className)) {
                continue;
            }

            $attribute->resolveDiscriminator($event, $classMetadata, $className);
        }

        $methodAttributes = $attributes[AttributeReader::TARGET_METHOD][$className] ?? [];
        foreach ($methodAttributes as $method => $_) {
            foreach ($_ as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }

                if (!in_array(AttributeReader::TARGET_METHOD, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }

                if (!$attribute->supports(AttributeReader::TARGET_METHOD, $method)) {
                    continue;
                }

                $attribute->resolveDiscriminator($event, $classMetadata, $method);
            }
        }

        $propertyAttributes = $attributes[AttributeReader::TARGET_PROPERTY][$className] ?? [];
        foreach ($propertyAttributes as $property => $_) {
            foreach ($_ as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }

                if (!in_array(AttributeReader::TARGET_PROPERTY, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }

                if (!$attribute->supports(AttributeReader::TARGET_PROPERTY, $property)) {
                    continue;
                }

                $attribute->resolveDiscriminator($event, $classMetadata, $property);
            }
        }
    }

    public function preQuery(DoctrineQueryEventArgs $event): void
    {
        $classMetadata = $event->getClassMetadata();
        $className = $event->getEntityName() ?? null;
        $attributes = $this->attributeReader->getAttributes($className);

        // Class attributes
        $classAttributes = $attributes[AttributeReader::TARGET_CLASS][$className] ?? [];
        foreach ($classAttributes as $attribute) {
            if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                continue;
            }

            if (!in_array(AttributeReader::TARGET_CLASS, $this->attributeReader->getAttributeTargets($attribute))) {
                continue;
            }
            if (!$attribute->supports(AttributeReader::TARGET_CLASS, $className, $classMetadata)) {
                continue;
            }
            $attribute->preQuery($event, $classMetadata, $className);
        }

        // Method attributes
        $methodAttributes = $attributes[AttributeReader::TARGET_METHOD][$className] ?? [];
        foreach ($methodAttributes as $method => $_) {
            foreach ($_ as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }
                
                if (!in_array(AttributeReader::TARGET_METHOD, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }
                if (!$attribute->supports(AttributeReader::TARGET_METHOD, $method, $classMetadata)) {
                    continue;
                }
                $attribute->preQuery($event, $classMetadata, $method);
            }
        }

        // Property attributes
        $propertyAttributes = $attributes[AttributeReader::TARGET_PROPERTY][$className] ?? [];
        foreach ($propertyAttributes as $property => $_) {
            foreach ($_ as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }

                if (!in_array(AttributeReader::TARGET_PROPERTY, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }
                if (!$attribute->supports(AttributeReader::TARGET_PROPERTY, $property, $classMetadata)) {
                    continue;
                }
                $attribute->preQuery($event, $classMetadata, $property);
            }
        }
    }

    public function onQuery(DoctrineQueryEventArgs $event)
    {
        $classMetadata = $event->getClassMetadata();
        $className = $event->getEntityName() ?? null;

        $attributes = $this->attributeReader->getAttributes($className);

        // Class attributes
        $classAttributes = $attributes[AttributeReader::TARGET_CLASS][$className] ?? [];
        foreach ($classAttributes as $attribute) {
            if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                continue;
            }
            if (!in_array(AttributeReader::TARGET_CLASS, $this->attributeReader->getAttributeTargets($attribute))) {
                continue;
            }
            if (!$attribute->supports(AttributeReader::TARGET_CLASS, $className, $classMetadata)) {
                continue;
            }
            $attribute->onQuery($event, $classMetadata, $className);
        }

        // Method attributes
        $methodAttributes = $attributes[AttributeReader::TARGET_METHOD][$className] ?? [];
        foreach ($methodAttributes as $method => $_) {
            foreach ($_ as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }
                if (!in_array(AttributeReader::TARGET_METHOD, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }
                if (!$attribute->supports(AttributeReader::TARGET_METHOD, $method, $classMetadata)) {
                    continue;
                }
                $attribute->onQuery($event, $classMetadata, $method);
            }
        }

        // Property attributes
        $propertyAttributes = $attributes[AttributeReader::TARGET_PROPERTY][$className] ?? [];
        foreach ($propertyAttributes as $property => $_) {
            foreach ($_ as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }
                if (!in_array(AttributeReader::TARGET_PROPERTY, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }
                if (!$attribute->supports(AttributeReader::TARGET_PROPERTY, $property, $classMetadata)) {
                    continue;
                }
                $attribute->onQuery($event, $classMetadata, $property);
            }
        }
    }

    public function postQuery(DoctrineQueryEventArgs $event)
    {
        $classMetadata = $event->getClassMetadata();
        $className = $event->getEntityName() ?? null;

        $attributes = $this->attributeReader->getAttributes($className);

        // Class attributes
        $classAttributes = $attributes[AttributeReader::TARGET_CLASS][$className] ?? [];
        foreach ($classAttributes as $attribute) {
            if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                continue;
            }
            if (!in_array(AttributeReader::TARGET_CLASS, $this->attributeReader->getAttributeTargets($attribute))) {
                continue;
            }
            if (!$attribute->supports(AttributeReader::TARGET_CLASS, $className, $classMetadata)) {
                continue;
            }
            $attribute->postQuery($event, $classMetadata, $className);
        }

        // Method attributes
        $methodAttributes = $attributes[AttributeReader::TARGET_METHOD][$className] ?? [];
        foreach ($methodAttributes as $method => $_) {
            foreach ($_ as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }
                if (!in_array(AttributeReader::TARGET_METHOD, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }
                if (!$attribute->supports(AttributeReader::TARGET_METHOD, $method, $classMetadata)) {
                    continue;
                }
                $attribute->postQuery($event, $classMetadata, $method);
            }
        }

        // Property attributes
        $propertyAttributes = $attributes[AttributeReader::TARGET_PROPERTY][$className] ?? [];
        foreach ($propertyAttributes as $property => $_) {
            foreach ($_ as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }
                if (!in_array(AttributeReader::TARGET_PROPERTY, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }
                if (!$attribute->supports(AttributeReader::TARGET_PROPERTY, $property, $classMetadata)) {
                    continue;
                }
                $attribute->postQuery($event, $classMetadata, $property);
            }
        }
    }

    public function preFlush(PreFlushEventArgs $event)
    {
        $uow = $event->getObjectManager()->getUnitOfWork();
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->entityInsertionBuffer[] = $entity;
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->entityUpdateBuffer[] = $entity;
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->entityDeletionBuffer[] = $entity;
        }

        $entities = array_merge($this->entityInsertionBuffer, $this->entityUpdateBuffer, $this->entityDeletionBuffer);
        foreach ($entities as $entity) {

            $className = get_class($entity);
            $classMetadata = $this->entityManager->getClassMetadata($className);
            $attributes = $this->attributeReader->getAttributes($className);

            $changeSet = $uow->getEntityChangeSet($entity);
            if(empty($changeSet) && !$entity->getId()) $changeSet = cast_to_array($entity);
            
            $propertyAttributes = $attributes[AttributeReader::TARGET_PROPERTY][$className] ?? [];
            foreach ($propertyAttributes as $property => $_) {
                if (!array_key_exists($property, $changeSet)) {
                    continue;
                }

                foreach ($_ as $attribute) {
                    if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                        continue;
                    }

                    if (!in_array(AttributeReader::TARGET_PROPERTY, $this->attributeReader->getAttributeTargets($attribute))) {
                        continue;
                    }

                    if (!$attribute->supports(AttributeReader::TARGET_PROPERTY, $property, $entity)) {
                        continue;
                    }

                    $attribute->preFlush($event, $classMetadata, $entity, $property);
                }
            }

            $classAttributes = $attributes[AttributeReader::TARGET_CLASS][$className] ?? [];
            foreach ($classAttributes as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }

                if (!in_array(AttributeReader::TARGET_CLASS, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }

                if (!$attribute->supports(AttributeReader::TARGET_CLASS, $className, $entity)) {
                    continue;
                }

                $attribute->preFlush($event, $classMetadata, $entity);
                $this->entityCandidateBuffer[] = $entity;
            }
        }
    }

    protected array $entityCandidateBuffer = [];
    protected array $entityInsertionBuffer = [];
    protected array $entityUpdateBuffer = [];
    protected array $entityDeletionBuffer = [];

    public function onFlush(OnFlushEventArgs $event)
    {
        $uow = $event->getObjectManager()->getUnitOfWork();
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->entityInsertionBuffer[] = $entity;
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->entityUpdateBuffer[] = $entity;
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->entityDeletionBuffer[] = $entity;
        }

        $entities = array_merge($this->entityInsertionBuffer, $this->entityUpdateBuffer, $this->entityDeletionBuffer);
        foreach ($entities as $entity) {
            $className = get_class($entity);
            $classMetadata = $this->entityManager->getClassMetadata($className);

            $attributes = $this->attributeReader->getAttributes($className);
            $changeSet = $uow->getEntityChangeSet($entity);
            if(empty($changeSet) && !$entity->getId()) $changeSet = cast_to_array($entity);

            $propertyAttributes = $attributes[AttributeReader::TARGET_PROPERTY][$className] ?? [];
            foreach ($propertyAttributes as $property => $_) {

                if (!array_key_exists($property, $changeSet)) {
                    continue;
                }

                foreach ($_ as $attribute) {
                    if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                        continue;
                    }

                    if (!in_array(AttributeReader::TARGET_PROPERTY, $this->attributeReader->getAttributeTargets($attribute))) {
                        continue;
                    }

                    if (!$attribute->supports(AttributeReader::TARGET_PROPERTY, $property, $entity)) {
                        continue;
                    }

                    $attribute->onFlush($event, $classMetadata, $entity, $property);
                }
            }

            $classAttributes = $attributes[AttributeReader::TARGET_CLASS][$className] ?? [];
            foreach ($classAttributes as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }

                if (!in_array(AttributeReader::TARGET_CLASS, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }

                if (!$attribute->supports(AttributeReader::TARGET_CLASS, $className, $entity)) {
                    continue;
                }

                $attribute->onFlush($event, $classMetadata, $entity);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event)
    {
        $uow = $event->getObjectManager()->getUnitOfWork();

        $entities = array_merge($this->entityCandidateBuffer, $this->entityInsertionBuffer, $this->entityUpdateBuffer, $this->entityDeletionBuffer);
        foreach ($entities as $entity) {
            $className = get_class($entity);
            $classMetadata = $this->entityManager->getClassMetadata($className);

            $attributes = $this->attributeReader->getAttributes($className);

            $changeSet = $uow->getEntityChangeSet($entity);
            if(empty($changeSet) && !$entity->getId()) $changeSet = cast_to_array($entity);
            
            $propertyAttributes = $attributes[AttributeReader::TARGET_PROPERTY][$className] ?? [];
            foreach ($propertyAttributes as $property => $_) {
                if (!array_key_exists($property, $changeSet)) {
                    continue;
                }

                foreach ($_ as $attribute) {
                    if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                        continue;
                    }

                    if (!in_array(AttributeReader::TARGET_PROPERTY, $this->attributeReader->getAttributeTargets($attribute))) {
                        continue;
                    }

                    if (!$attribute->supports(AttributeReader::TARGET_PROPERTY, $property, $entity)) {
                        continue;
                    }

                    $attribute->postFlush($event, $classMetadata, $entity, $property);
                }
            }

            $classAttributes = $attributes[AttributeReader::TARGET_CLASS][$className] ?? [];
            foreach ($classAttributes as $attribute) {
                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }

                if (!in_array(AttributeReader::TARGET_CLASS, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }

                if (!$attribute->supports(AttributeReader::TARGET_CLASS, $className, $entity)) {
                    continue;
                }

                $attribute->postFlush($event, $classMetadata, $entity);
            }
        }

        $this->entityInsertionBuffer = [];
        $this->entityUpdateBuffer = [];
        $this->entityDeletionBuffer = [];
        $this->entityCandidateBuffer = [];
    }

    /**
     * @param LifecycleEventArgs $event
     * @param $eventName
     * @return void
     * @throws \Exception
     */
    protected function onLifecycle(LifecycleEventArgs $event, $eventName)
    {
        $entity = $event->getObject();

        $className = get_class($entity);
        $classMetadata = $this->entityManager->getClassMetadata($className);

        $attributes = $this->attributeReader->getAttributes($className);

        $propertyAttributes = $attributes[AttributeReader::TARGET_PROPERTY][$className] ?? [];
        foreach ($propertyAttributes as $property => $_) {
            foreach ($_ as $attribute) {

                if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                    continue;
                }

                if (!in_array(AttributeReader::TARGET_PROPERTY, $this->attributeReader->getAttributeTargets($attribute))) {
                    continue;
                }

                if (!$attribute->supports(AttributeReader::TARGET_PROPERTY, $property, $entity)) {
                    continue;
                }

                $attribute->{$eventName}($event, $classMetadata, $entity, $property);
            }
        }

        $classAttributes = $attributes[AttributeReader::TARGET_CLASS][$className] ?? [];
        foreach ($classAttributes as $attribute) {
            if (!is_subclass_of($attribute, AbstractAttribute::class)) {
                continue;
            }

            if (!in_array(AttributeReader::TARGET_CLASS, $this->attributeReader->getAttributeTargets($attribute))) {
                continue;
            }

            if (!$attribute->supports(AttributeReader::TARGET_CLASS, $className, $entity)) {
                continue;
            }

            $attribute->{$eventName}($event, $classMetadata, $entity);
        }
    }

    public function prePersist(LifecycleEventArgs $event)
    {
        $this->onLifecycle($event, __FUNCTION__);
    }

    public function preUpdate(LifecycleEventArgs $event)
    {
        $this->onLifecycle($event, __FUNCTION__);
    }

    public function preRemove(LifecycleEventArgs $event)
    {
        $this->onLifecycle($event, __FUNCTION__);
    }

    public function postLoad(LifecycleEventArgs $event)
    {
        $this->onLifecycle($event, __FUNCTION__);
    }

    public function postPersist(LifecycleEventArgs $event)
    {
        $this->onLifecycle($event, __FUNCTION__);
    }

    public function postUpdate(LifecycleEventArgs $event)
    {
        $this->onLifecycle($event, __FUNCTION__);
    }

    public function postRemove(LifecycleEventArgs $event)
    {
        $this->onLifecycle($event, __FUNCTION__);
    }
}
