<?php

namespace Base\DatabaseSubscriber;

use Base\Database\Mapping\ClassMetadataManipulator;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

/**
 *
 */
class TrackingPolicySubscriber implements EventSubscriberInterface
{
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getSubscribedEvents(): array
    {
        return [Events::loadClassMetadata];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args)
    {
        /**
         * @var ClassMetadata $args
         */
        $classMetadata = $args->getClassMetadata();

        if ($trackingPolicy = $this->classMetadataManipulator->getTrackingPolicy($classMetadata->getName())) {
            $classMetadata->setChangeTrackingPolicy($trackingPolicy);
        }
    }

    // protected function onLifecycle(LifecycleEventArgs $event, $eventName)
    // {
    //     $object = $event->getObject();
    //     if($object?->getId() === null) return;

    //     $objectManager = $event->getObjectManager();
    //     $objectManager->getCache()->evictEntity(get_class($object), $object->getId());
    // }

    // public function postPersist(LifecycleEventArgs $event) { $this->onLifecycle($event, __FUNCTION__); }
    // public function postUpdate (LifecycleEventArgs $event) { $this->onLifecycle($event, __FUNCTION__); }
    // public function postRemove (LifecycleEventArgs $event) { $this->onLifecycle($event, __FUNCTION__); }
}
