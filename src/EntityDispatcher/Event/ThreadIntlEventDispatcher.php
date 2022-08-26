<?php

namespace Base\EntityDispatcher\Event;

use Base\Entity\ThreadIntl;
use Base\EntityDispatcher\AbstractEventDispatcher;
use Doctrine\ORM\Event\LifecycleEventArgs;

class ThreadIntlEventDispatcher extends AbstractEventDispatcher
{
    public function supports(mixed $subject): bool
    {
        return $subject instanceof ThreadIntl;
    }

    public function onPersist(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        $this->events[spl_object_id($subject)][] = ThreadIntlEvent::CLEANUP;
    }

    public function onUpdate(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        $this->events[spl_object_id($subject)][] = ThreadIntlEvent::CLEANUP;
    }
}
