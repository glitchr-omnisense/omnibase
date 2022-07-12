<?php

namespace Base\EntityDispatcher\Event;

use Base\Entity\ThreadTranslation;
use Base\EntityDispatcher\AbstractEventDispatcher;
use Doctrine\ORM\Event\LifecycleEventArgs;

class ThreadTranslationEventDispatcher extends AbstractEventDispatcher
{
    public function supports(mixed $subject): bool
    {
        return $subject instanceof ThreadTranslation;
    }

    public function onPersist(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        $this->events[spl_object_id($subject)][] = ThreadTranslationEvent::CLEANUP;
    }

    public function onUpdate(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        $this->events[spl_object_id($subject)][] = ThreadTranslationEvent::CLEANUP;
    }
}
