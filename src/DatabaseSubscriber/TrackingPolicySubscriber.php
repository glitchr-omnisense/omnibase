<?php

namespace Base\DatabaseSubscriber;

use Base\Database\Mapping\ClassMetadataManipulator;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;

class TrackingPolicySubscriber
{
    /**
     * @var ClassMetadataManipulator
     */
    protected $classMetadataManipulator;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
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
}
