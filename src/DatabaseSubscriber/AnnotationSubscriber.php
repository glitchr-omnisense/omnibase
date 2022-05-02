<?php

namespace Base\DatabaseSubscriber;

use Base\Annotations\AnnotationReader;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;

class AnnotationSubscriber implements EventSubscriberInterface {

    private $entityManager;
    protected array $subscriberHistory = [];

    public function __construct(EntityManager $entityManager, AnnotationReader $annotationReader)
    {
        $this->entityManager    = $entityManager;
        $this->annotationReader = $annotationReader;
    }

    public function getSubscribedEvents(): array
    {
        return [

            Events::loadClassMetadata,
            Events::postLoad,

            Events::onFlush, 
            Events::prePersist,  Events::preUpdate,  Events::preRemove,
            Events::postPersist, Events::postUpdate, Events::postRemove,
        ];
    }

    public function loadClassMetadata( LoadClassMetadataEventArgs $event ) {

        $className     = $event->getClassMetadata()->name;
        $classMetadata = $event->getClassMetadata();
        dump("----> ".$className);
        if (in_array($className, $this->subscriberHistory)) return;
        $this->subscriberHistory[] =  $className."::".__FUNCTION__;
        
        $annotations = $this->annotationReader->getAnnotations($className);
        $classAnnotations = $annotations[AnnotationReader::TARGET_CLASS][$className] ?? [];
        foreach ($classAnnotations as $entry) {

            if (!in_array(AnnotationReader::TARGET_CLASS, $this->annotationReader->getTargets($entry)))
                continue;

            if (!$entry->supports(AnnotationReader::TARGET_CLASS, $className, $classMetadata))
                continue;

            $entry->loadClassMetadata($classMetadata, AnnotationReader::TARGET_CLASS, $className);
        }

        $methodAnnotations = $annotations[AnnotationReader::TARGET_METHOD][$className] ?? [];
        foreach ($methodAnnotations as $method => $array) {

            foreach ($array as $entry) {

                if (!in_array(AnnotationReader::TARGET_METHOD, $this->annotationReader->getTargets($entry)))
                    continue;

                if (!$entry->supports(AnnotationReader::TARGET_METHOD, $method, $classMetadata))
                    continue;

                $entry->loadClassMetadata($classMetadata, AnnotationReader::TARGET_METHOD, $method);
            }
        }

        $propertyAnnotations = $annotations[AnnotationReader::TARGET_PROPERTY][$className] ?? [];
        foreach ($propertyAnnotations as $property => $array) {

            foreach ($array as $entry) {

                if (!in_array(AnnotationReader::TARGET_PROPERTY, $this->annotationReader->getTargets($entry)))
                    continue;

                if (!$entry->supports(AnnotationReader::TARGET_PROPERTY, $property, $classMetadata))
                    continue;

                $entry->loadClassMetadata($classMetadata, AnnotationReader::TARGET_PROPERTY, $property);
            }
        }
    }

    public function onFlush(OnFlushEventArgs $event)
    {
        $uow = $event->getEntityManager()->getUnitOfWork();

        $entities = [];
        foreach ($uow->getScheduledEntityInsertions() as $entity)
            $entities[] = $entity;
        foreach ($uow->getScheduledEntityUpdates() as $entity)
            $entities[] = $entity;
        foreach ($uow->getScheduledEntityDeletions() as $entity)
            $entities[] = $entity;

        foreach($entities as $entity) {

            $className = get_class($entity);
            $classMetadata  = $this->entityManager->getClassMetadata($className);

            if (in_array($className, $this->subscriberHistory)) return;
            $this->subscriberHistory[] = $className . "::" . __FUNCTION__;

            $annotations = $this->annotationReader->getAnnotations($className);

            $classAnnotations = $annotations[AnnotationReader::TARGET_CLASS][$className] ?? [];
            foreach ($classAnnotations as $entry) {

                if (!in_array(AnnotationReader::TARGET_CLASS, $this->annotationReader->getTargets($entry)))
                    continue;

                if (!$entry->supports(AnnotationReader::TARGET_CLASS, $className, $entity))
                    continue;

                $entry->onFlush($event, $classMetadata, $entity);
            }

            $changeSet = $uow->getEntityChangeSet($entity);
            $propertyAnnotations = $annotations[AnnotationReader::TARGET_PROPERTY][$className] ?? [];
            foreach ($propertyAnnotations as $property => $array) {

                if(!array_key_exists($property, $changeSet))
                    continue;

                foreach ($array as $entry) {

                    if (!in_array(AnnotationReader::TARGET_PROPERTY, $this->annotationReader->getTargets($entry)))
                        continue;

                    if (!$entry->supports(AnnotationReader::TARGET_PROPERTY, $property, $entity))
                        continue;

                    $entry->onFlush($event, $classMetadata, $entity, $property);
                }
            }
        }
    }

    protected function onLifecycle(LifecycleEventArgs $event, $eventName)
    {
        $entity         = $event->getObject();

        $className      = get_class($entity);
        $classMetadata  = $this->entityManager->getClassMetadata($className);
        if (in_array($className, $this->subscriberHistory)) return;
        $this->subscriberHistory[] = $className . "::" . __FUNCTION__;
        
        $annotations    = $this->annotationReader->getAnnotations($className);

        $classAnnotations = $annotations[AnnotationReader::TARGET_CLASS][$className] ?? [];
        foreach ($classAnnotations as $entry) {

            if (!in_array(AnnotationReader::TARGET_CLASS, $this->annotationReader->getTargets($entry)))
                continue;

            if (!$entry->supports(AnnotationReader::TARGET_CLASS, $className, $entity))
                continue;

            $entry->{$eventName}($event, $classMetadata, $entity);
        }

        $propertyAnnotations = $annotations[AnnotationReader::TARGET_PROPERTY][$className] ?? [];
        foreach ($propertyAnnotations as $property => $array) {

            foreach ($array as $entry) {

                if (!in_array(AnnotationReader::TARGET_PROPERTY, $this->annotationReader->getTargets($entry)))
                    continue;
                
                if (!$entry->supports(AnnotationReader::TARGET_PROPERTY, $property, $entity))
                    continue;
                
                $entry->{$eventName}($event, $classMetadata, $entity, $property);
            }
        }
    }

    public function prePersist(LifecycleEventArgs $event) { $this->onLifecycle($event, __FUNCTION__); }
    public function preUpdate (LifecycleEventArgs $event) { $this->onLifecycle($event, __FUNCTION__); }
    public function preRemove (LifecycleEventArgs $event) { $this->onLifecycle($event, __FUNCTION__); }

    public function postLoad   (LifecycleEventArgs $event) { $this->onLifecycle($event, __FUNCTION__); }
    public function postPersist(LifecycleEventArgs $event) { $this->onLifecycle($event, __FUNCTION__); }
    public function postUpdate (LifecycleEventArgs $event) { $this->onLifecycle($event, __FUNCTION__); }
    public function postRemove (LifecycleEventArgs $event) { $this->onLifecycle($event, __FUNCTION__); }
}
