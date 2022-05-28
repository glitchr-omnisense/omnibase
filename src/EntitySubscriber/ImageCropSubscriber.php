<?php

namespace Base\EntitySubscriber;

use Base\Entity\Layout\ImageCrop;
use Base\EntityEvent\ImageCropEvent;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class ImageCropSubscriber implements EventSubscriberInterface
{
    public function getSubscribedEvents() : array
    {
        return [
            Events::postUpdate,
            Events::preUpdate,
            Events::postPersist,
            Events::prePersist,
            Events::postLoad
        ];
    }

    public function prePersist(LifecycleEventArgs $event)
    {
        $thread = $event->getObject();
        if (!$thread instanceof ImageCrop) return;

        dump($thread);
        exit(1);
    }

    public function preUpdate(LifecycleEventArgs $event)
    {
        $thread = $event->getObject();
        if (!$thread instanceof ImageCrop) return;

        dump($thread);
        exit(1);
    }

    public function postPersist(LifecycleEventArgs $event): void
    {
        $thread = $event->getObject();
        if (!$thread instanceof ImageCrop) return;

        dump($thread);
        exit(1);
    }

    public function postUpdate(LifecycleEventArgs $event): void
    {
        $thread = $event->getObject();
        if (!$thread instanceof ImageCrop) return;

        dump($thread);
        exit(1);
    }
    public function postLoad(LifecycleEventArgs $event): void
    {
        $thread = $event->getObject();
        if (!$thread instanceof ImageCrop) return;

        dump($thread);
        exit(1);
    }
}
