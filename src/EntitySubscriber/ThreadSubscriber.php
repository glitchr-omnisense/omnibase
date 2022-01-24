<?php

namespace Base\EntitySubscriber;

use Base\Entity\Thread;
use Base\EntityEvent\ThreadEvent;
use Base\Entity\Thread as EntityThread;
use Base\Enum\ThreadState;
use Base\Service\BaseService;
use Base\Form\Type\RoleType;

use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ThreadSubscriber implements EventSubscriber
{
    protected array $events;
    public function __construct($dispatcher) {
        $this->dispatcher = $dispatcher;
        $this->events = [];
    }

    public function getSubscribedEvents() : array
    {
        return [
            Events::postUpdate,
            Events::preUpdate,
            Events::postPersist,
            Events::prePersist
        ];
    }

    public function prePersist(LifecycleEventArgs $event)
    {
        $thread = $event->getObject();
        if (!$thread instanceof Thread) return;

        // Update publishable articles
        if ($thread->isScheduled() && $thread->isPublishable())
            $thread->setState(ThreadState::PUBLISHED);

        if ($thread->isPublished())
            $this->events[spl_object_id($thread)][] = ThreadEvent::PUBLISH;
    }

    public function preUpdate(LifecycleEventArgs $event)
    {
        $thread = $event->getObject();
        if (!$thread instanceof Thread) return;

        // Update publishable articles
        if ($thread->isScheduled() && $thread->isPublishable()) {

            $thread->setState(ThreadState::PUBLISHED);
            $this->events[spl_object_id($thread)][] = ThreadEvent::PUBLISH;
        }
    }

    public function postPersist(LifecycleEventArgs $event): void
    {
        $thread = $event->getObject();
        if (!$thread instanceof Thread) return;

        $this->dispatchEvents($thread);
    }

    public function postUpdate(LifecycleEventArgs $event): void
    {
        $thread = $event->getObject();
        if (!$thread instanceof Thread) return;

        $this->dispatchEvents($thread);
    }

    public function dispatchEvents($thread) {

        $id = spl_object_id($thread);
        if(!array_key_exists($id, $this->events)) return;

        foreach($this->events[$id] as $event)
            $this->dispatcher->dispatch(new ThreadEvent($thread), $event);
    }
}
