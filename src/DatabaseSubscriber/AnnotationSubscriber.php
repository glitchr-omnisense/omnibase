<?php

namespace Base\DatabaseSubscriber;

use Base\Database\AbstractAnnotation;
use Base\Database\AnnotationReader;
use Doctrine\Common\EventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;

class AnnotationSubscriber implements EventSubscriber {

    private $entityManager;
    protected array $subscriberHistory = [];

    public function __construct(EntityManager $entityManager, AnnotationReader $annotationReader)
    {
        $this->entityManager    = $entityManager;
        $this->annotationReader = $annotationReader;
    }

    public function getSubscribedEvents()
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

        if (in_array($className, $this->subscriberHistory)) return;
        $this->subscriberHistory[] =  $className."::".__FUNCTION__;

        $annotations = $this->annotationReader->getAnnotations($className);
        foreach ($annotations[AnnotationReader::TARGET_CLASS][$className] as $entry) {

            if (!in_array(AnnotationReader::TARGET_CLASS, $this->annotationReader->getTargets($entry)))
                continue;

            if (!$entry->supports($classMetadata, AnnotationReader::TARGET_CLASS, $className))
                continue;

            $entry->loadClassMetadata($classMetadata, AnnotationReader::TARGET_CLASS, $className);
        }

        $annotationMethods = $annotations[AnnotationReader::TARGET_METHOD][$className] ?? [];
        foreach ($annotationMethods as $method => $array) {

            foreach ($array as $entry) {

                if (!in_array(AnnotationReader::TARGET_METHOD, $this->annotationReader->getTargets($entry)))
                    continue;

                if (!$entry->supports($classMetadata, AnnotationReader::TARGET_METHOD, $method))
                    continue;

                $entry->loadClassMetadata($classMetadata, AnnotationReader::TARGET_METHOD, $method);
            }
        }

        $annotationProperties = $annotations[AnnotationReader::TARGET_PROPERTY][$className] ?? [];
        foreach ($annotationProperties as $property => $array) {

            foreach ($array as $entry) {

                if (!in_array(AnnotationReader::TARGET_PROPERTY, $this->annotationReader->getTargets($entry)))
                    continue;

                if (!$entry->supports($classMetadata, AnnotationReader::TARGET_PROPERTY, $property))
                    continue;

                $entry->loadClassMetadata($classMetadata, AnnotationReader::TARGET_PROPERTY, $property);
            }
        }
    }

    public function onFlush(OnFlushEventArgs $event)
    {
        $uow = $event->getEntityManager()->getUnitOfWork();
        $uow->computeChangeSets();

        $entities = [];
        foreach ($uow->getScheduledEntityInsertions() as $entity)
            $entities[] = $entity;
        foreach ($uow->getScheduledEntityUpdates() as $entity)
            $entities[] = $entity;
        foreach ($uow->getScheduledEntityDeletions() as $entity)
            $entities[] = $entity;

        foreach($entities as $entity) {

            $className = get_class($entity);

            if (in_array($className, $this->subscriberHistory)) return;
            $this->subscriberHistory[] = $className . "::" . __FUNCTION__;

            $classMetadata  = $this->entityManager->getClassMetadata($className);
            $annotations = $this->annotationReader->getAnnotations($className);
            foreach ($annotations[AnnotationReader::TARGET_CLASS][$className] as $entry) {

                if (!in_array(AnnotationReader::TARGET_CLASS, $this->annotationReader->getTargets($entry)))
                    continue;

                if (!$entry->supports($classMetadata, AnnotationReader::TARGET_CLASS, $className, $entity))
                    continue;

                $entry->onFlush($event, $classMetadata, $entity);
            }

            /** ** TARGET_METHOD is missing on purpose ** */

            $changeSet = $uow->getEntityChangeSet($entity);
            foreach ($annotations[AnnotationReader::TARGET_PROPERTY][$className] as $property => $array) {

                if(!array_key_exists($property, $changeSet))
                    continue;

                foreach ($array as $entry) {

                    if (!in_array(AnnotationReader::TARGET_PROPERTY, $this->annotationReader->getTargets($entry)))
                        continue;

                    if (!$entry->supports($classMetadata, AnnotationReader::TARGET_PROPERTY, $property, $entity))
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

        if (in_array($className, $this->subscriberHistory)) return;
        $this->subscriberHistory[] = $className . "::" . __FUNCTION__;

        $classMetadata  = $this->entityManager->getClassMetadata($className);
        $annotations    = $this->annotationReader->getAnnotations($className);
        
        foreach ($annotations[AnnotationReader::TARGET_CLASS][$className] as $entry) {

            if (!in_array(AnnotationReader::TARGET_CLASS, $this->annotationReader->getTargets($entry)))
                continue;

            if (!$entry->supports($classMetadata, AnnotationReader::TARGET_CLASS, $className, $entity))
                continue;

            $entry->{$eventName}($event, $classMetadata, $entity);
        }

        foreach ($annotations[AnnotationReader::TARGET_PROPERTY][$className] as $property => $array) {

            foreach ($array as $entry) {

                if (!in_array(AnnotationReader::TARGET_PROPERTY, $this->annotationReader->getTargets($entry)))
                    continue;

                if (!$entry->supports($classMetadata, AnnotationReader::TARGET_PROPERTY, $property, $entity))
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
