<?php

namespace Base\DatabaseSubscriber;

use Base\Database\Factory\ClassMetadataManipulator;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

class DoctrineTrackingPolicySubscriber implements EventSubscriberInterface
{
    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getSubscribedEvents():array
    {
        return [
            Events::loadClassMetadata
        ];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args)
    {
        $classMetadata = $args->getClassMetadata();

        if ( ($trackingPolicy = $this->classMetadataManipulator->getTrackingPolicy($classMetadata->getName())) )
            $classMetadata->setChangeTrackingPolicy($trackingPolicy);
    }
}
