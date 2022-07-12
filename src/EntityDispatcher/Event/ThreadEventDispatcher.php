<?php

namespace Base\EntityDispatcher\Event;

use Base\Entity\Thread;
use Base\EntityDispatcher\AbstractEventDispatcher;
use Base\Enum\ThreadState;
use Doctrine\ORM\Event\LifecycleEventArgs;

class ThreadEventDispatcher extends AbstractEventDispatcher
{
    public function supports(mixed $subject): bool
    {
        return $subject instanceof Thread;
    }

    public function onPersist(LifecycleEventArgs $event)
    {
        $thread = $event->getObject();

        if ($thread->isScheduled())
            $this->events[spl_object_id($thread)][] = ThreadEvent::SCHEDULED;
        if ($thread->isPublished())
            $this->events[spl_object_id($thread)][] = ThreadEvent::PUBLISHED;
        if ($thread->isPublishable())
            $this->events[spl_object_id($thread)][] = ThreadEvent::PUBLISHABLE;
    }

    public function onUpdate(LifecycleEventArgs $event)
    {
        $thread = $event->getObject();

        // Update publishable articles
        if ($thread->isScheduled() && $thread->isPublishable()) {

            $thread->setState(ThreadState::PUBLISH);
            $this->events[spl_object_id($thread)][] = ThreadEvent::PUBLISHED;
        }
    }
}
