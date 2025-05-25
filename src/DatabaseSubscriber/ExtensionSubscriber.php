<?php

namespace Base\DatabaseSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class ExtensionSubscriber // not used yet.
{
    protected EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {

    }

    public function postLoad(LifecycleEventArgs $args)
    {

    }

    public function prePersist(LifecycleEventArgs $event)
    {

    }

    public function preUpdate(LifecycleEventArgs $event)
    {

    }

    public function preRemove(LifecycleEventArgs $event)
    {

    }
    
    public function postPersist(LifecycleEventArgs $event)
    {

    }

    public function postUpdate(LifecycleEventArgs $event)
    {

    }

    public function postRemove(LifecycleEventArgs $event)
    {

    }
}