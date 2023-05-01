<?php

namespace Base\EntityDispatcher\Event;

use Base\Entity\Thread;
use Base\EntityDispatcher\AbstractEventDispatcher;
use DateTime;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 *
 */
class ThreadEventDispatcher extends AbstractEventDispatcher
{
    public function supports(mixed $subject): bool
    {
        return $subject instanceof Thread;
    }

    public function onPersist(LifecycleEventArgs $event)
    {
        $thread = $event->getObject();

        if ($thread->isScheduled()) {
            $this->addEvent(ThreadEvent::SCHEDULED, $thread);
        }
        if ($thread->isPublished()) {
            $this->addEvent(ThreadEvent::PUBLISHED, $thread);
        }
        if ($thread->isPublishable()) {
            $this->addEvent(ThreadEvent::PUBLISHABLE, $thread);
        }
    }

    public function onUpdate(LifecycleEventArgs $event)
    {
        /**
         * @var Thread $event
         */
        $thread = $event->getObject();

        // Check if scheduled
        if ($thread->isPublishedAt() > new DateTime("now") && $thread->isPublished()) {
            $this->addEvent(ThreadEvent::SCHEDULED, $thread);
        }

        // Update if publishable
        if ($thread->isPublishable()) {
            $this->addEvent(ThreadEvent::PUBLISHABLE, $thread);
        }

        // Update if published
        if ($thread->isPublished()) {
            $this->addEvent(ThreadEvent::PUBLISHED, $thread);
        }
    }
}
