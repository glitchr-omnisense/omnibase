<?php

namespace Base\EntityDispatcher\Event;

use Base\Entity\Thread;
use Base\EntityDispatcher\AbstractEventDispatcher;
use DateTime;
use Omnibridge\Persistence\Lifecycle;

class ThreadEventDispatcher extends AbstractEventDispatcher
{
    public function supports(mixed $subject): bool
    {
        return $subject instanceof Thread;
    }

    public function persisting(Lifecycle\PrePersist $event): void
    {
        $thread = $event->entity;

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

    public function updating(Lifecycle\PreUpdate $event): void
    {
        /** @var Thread $thread */
        $thread = $event->entity;

        if ($thread->isPublishedAt() > new DateTime("now") && $thread->isPublished()) {
            $this->addEvent(ThreadEvent::SCHEDULED, $thread);
        }
        if ($thread->isPublishable()) {
            $this->addEvent(ThreadEvent::PUBLISHABLE, $thread);
        }
        if ($thread->isPublished()) {
            $this->addEvent(ThreadEvent::PUBLISHED, $thread);
        }
    }
}
