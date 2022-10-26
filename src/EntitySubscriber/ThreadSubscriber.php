<?php

namespace Base\EntitySubscriber;

use Base\EntityDispatcher\Event\ThreadEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ThreadSubscriber implements EventSubscriberInterface
{
    protected array $events;

    public static function getSubscribedEvents() : array
    {
        return
        [
            ThreadEvent::SCHEDULED   => ['onSchedule'],
            ThreadEvent::PUBLISHABLE => ['onPublishable'],
            ThreadEvent::PUBLISHED   => ['onPublished'],
        ];
    }

    public function onSchedule(ThreadEvent $event)
    {
    }

    public function onPublishable(ThreadEvent $event)
    {
    }

    public function onPublished(ThreadEvent $event)
    {
    }
}
