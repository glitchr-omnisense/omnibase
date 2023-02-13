<?php

namespace Base\EntityDispatcher;

use Base\Database\Entity\EntityHydrator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Exception;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;

abstract class AbstractEventDispatcher implements EventDispatcherInterface
{
    protected array $events;

    /**
     * @var SymfonyEventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var EntityHydratorInterface
     */ 
    protected $entityHydrator;
    
    /**
     * @var EntityManagerInterface
     */ 
    protected $entityManager;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    public function __construct(SymfonyEventDispatcherInterface $dispatcher, EntityHydrator $entityHydrator, EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->dispatcher    = $dispatcher;
        $this->entityManager = $entityManager;
        $this->entityHydrator = $entityHydrator;

        $this->requestStack = $requestStack;
        $this->events        = [];
    }

    public const DISPATCHER_SUFFIX = "Dispatcher";
    public function getSubscribedEvents() : array
    {
        return
        [
            Events::preUpdate,
            Events::postUpdate,

            Events::prePersist,
            Events::postPersist,

            Events::preRemove,
            Events::postRemove,
        ];
    }

    public static function getEventClass()
    {
        $class = static::class;

        if(!str_ends_with(static::class, self::DISPATCHER_SUFFIX))
            throw new Exception("Unexpected dispatcher name. \"".$class."\" must ends with \"". self::DISPATCHER_SUFFIX."\"");

        return substr($class, 0, -strlen(self::DISPATCHER_SUFFIX));
    }

    public function addEvent(string $event, mixed $subject)
    {
        $id = spl_object_id($subject);
        if(!array_key_exists($id, $this->events))
            $this->events[$id] = [];

        if(!array_key_exists($event, $this->events[$id]))
            $this->events[$id][$event] = true;
    }

    public function dispatchEvents(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null) return;

        $id = spl_object_id($subject);
        if (!array_key_exists($id, $this->events)) return;

        $reflush = false;
        $eventClass = $this->getEventClass();

        $request = $this->requestStack->getCurrentRequest();
        foreach ($this->events[$id] as $eventName => $alreadyTriggered) {

            if($alreadyTriggered === false) continue;

            $this->events[$id][$eventName] = false;
            $this->dispatcher->dispatch(new $eventClass($event, $request), $eventName);
            $reflush = true;
        }

        if($reflush)
            $event->getObjectManager()->flush();
    }

    public function getEntityManager() { return $this->entityManager; }

    public function prePersist(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null || !$this->supports($subject)) return;

        $this->onPersist($event);
    }

    public function preUpdate(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null || !$this->supports($subject)) return;

        $this->onUpdate($event);
    }

    public function preRemove(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null || !$this->supports($subject)) return;

        $this->onRemove($event);
    }

    public function onPersist(LifecycleEventArgs $event) { }
    public function onUpdate (LifecycleEventArgs $event) { }
    public function onRemove (LifecycleEventArgs $event) { }

    public function postPersist(LifecycleEventArgs $event): void { $this->dispatchEvents($event); }
    public function postUpdate (LifecycleEventArgs $event): void { $this->dispatchEvents($event); }
    public function postRemove (LifecycleEventArgs $event): void { $this->dispatchEvents($event); }
}
