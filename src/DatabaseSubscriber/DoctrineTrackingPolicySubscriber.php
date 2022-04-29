<?php

namespace Base\DatabaseSubscriber;

use Base\Database\Factory\ClassMetadataManipulator;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

class DoctrineTrackingPolicySubscriber implements EventSubscriber
{
    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata
        ];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args)
    {
        $classMetadata = $args->getClassMetadata();

        if ( ($this->classMetadataManipulator->getTrackingPolicy($classMetadata->getName())) )
            $classMetadata->setChangeTrackingPolicy($trackingPolicy);
    }
}
