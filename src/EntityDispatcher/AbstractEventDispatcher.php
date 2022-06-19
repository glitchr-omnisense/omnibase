<?php

namespace Base\EntityDispatcher;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Exception;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;

abstract class AbstractEventDispatcher implements EventDispatcherInterface
{
    protected array $events;
    public function __construct(SymfonyEventDispatcherInterface $dispatcher, EntityManagerInterface $entityManager)
    {
        $this->dispatcher    = $dispatcher;
        $this->entityManager = $entityManager;

        $this->events        = [];
    }

    public const __DISPATCHER_SUFFIX__ = "Dispatcher";
    public function getSubscribedEvents() : array
    {
        return [

            Events::preUpdate,
            Events::postUpdate,

            Events::prePersist,
            Events::postPersist,

            Events::preRemove,
            Events::postRemove
        ];
    }

    public static function getEventClass()
    {
        $class = static::class;

        if(!str_ends_with(static::class, self::__DISPATCHER_SUFFIX__))
            throw new Exception("Unexpected dispatcher name. \"".$class."\" must ends with \"". self::__DISPATCHER_SUFFIX__."\"");

        return substr($class, 0, -strlen(self::__DISPATCHER_SUFFIX__));
    }

    public function addEvent(string $event, mixed $subject)
    {
        $id = spl_object_id($subject);

        if(!array_key_exists($id, $this->events))
            $this->events[$id] = [];

        if(!in_array($event, $this->events[$id]))
            $this->events[$id][$event] = false;
    }

    public function dispatchEvents(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null) return;

        $id = spl_object_id($subject);
        if (!array_key_exists($id, $this->events)) return;

        $eventClass = $this->getEventClass();
        foreach ($this->events[$id] as $event => &$trigger) {

            if(!$trigger) // Dispatch only once
                $this->dispatcher->dispatch(new $eventClass($event->getObject()), $event);

            $trigger = true;
        }
    }

    public function getEntityManager() { return $this->entityManager; }

    public function prePersist(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null || !$this->supports($event, $subject)) return;

        $this->onPersist($event);
    }

    public function preUpdate(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null || !$this->supports($event, $subject)) return;

        $this->onUpdate($event);
    }

    public function preRemove(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null || !$this->supports($event, $subject)) return;

        $this->onRemove($event);
    }

    public function onPersist(LifecycleEventArgs $event) { }
    public function onUpdate(LifecycleEventArgs $event) { }
    public function onRemove(LifecycleEventArgs $event) { }

    public function postPersist(LifecycleEventArgs $event): void { $this->dispatchEvents($event); }
    public function postUpdate (LifecycleEventArgs $event): void { $this->dispatchEvents($event); }
    public function postRemove (LifecycleEventArgs $event): void { $this->dispatchEvents($event); }
}
