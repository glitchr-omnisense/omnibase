<?php

namespace Base\EntityDispatcher;

use Base\Database\Entity\EntityHydratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Exception;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;

/**
 *
 */
abstract class AbstractEventDispatcher implements EventDispatcherInterface
{
    protected array $events;

    /**
     * @var SymfonyEventDispatcherInterface
     */
    protected SymfonyEventDispatcherInterface $dispatcher;

    /**
     * @var EntityHydratorInterface
     */
    protected $entityHydrator;

    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @var RequestStack
     */
    protected RequestStack $requestStack;

    public function __construct(SymfonyEventDispatcherInterface $dispatcher, EntityHydratorInterface $entityHydrator, EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->dispatcher = $dispatcher;
        $this->entityManager = $entityManager;
        $this->entityHydrator = $entityHydrator;

        $this->requestStack = $requestStack;
        $this->events = [];
    }

    public const DISPATCHER_SUFFIX = "Dispatcher";

    public function getSubscribedEvents(): array
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

    /**
     * @return string
     * @throws Exception
     */
    public static function getEventClass()
    {
        $class = static::class;

        if (!str_ends_with(static::class, self::DISPATCHER_SUFFIX)) {
            throw new Exception("Unexpected dispatcher name. \"" . $class . "\" must ends with \"" . self::DISPATCHER_SUFFIX . "\"");
        }

        return substr($class, 0, -strlen(self::DISPATCHER_SUFFIX));
    }

    public function addEvent(string $event, mixed $subject)
    {
        $id = spl_object_id($subject);
        if (!array_key_exists($id, $this->events)) {
            $this->events[$id] = [];
        }

        if (!array_key_exists($event, $this->events[$id])) {
            $this->events[$id][$event] = true;
        }
    }

    public function dispatchEvents(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null) {
            return;
        }

        $id = spl_object_id($subject);
        if (!array_key_exists($id, $this->events)) {
            return;
        }

        $reflush = false;
        $eventClass = $this->getEventClass();

        $request = $this->requestStack->getCurrentRequest();
        foreach ($this->events[$id] as $eventName => $alreadyTriggered) {
            if ($alreadyTriggered === false) {
                continue;
            }

            $this->events[$id][$eventName] = false;
            $this->dispatcher->dispatch(new $eventClass($event, $request), $eventName);
            $reflush = true;
        }

        if ($reflush) {
            $event->getObjectManager()->flush();
        }
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    public function prePersist(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null || !$this->supports($subject)) {
            return;
        }

        $this->onPersist($event);
    }

    public function preUpdate(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null || !$this->supports($subject)) {
            return;
        }

        $this->onUpdate($event);
    }

    public function preRemove(LifecycleEventArgs $event)
    {
        $subject = $event->getObject();
        if ($subject == null || !$this->supports($subject)) {
            return;
        }

        $this->onRemove($event);
    }

    public function onPersist(LifecycleEventArgs $event)
    {
    }

    public function onUpdate(LifecycleEventArgs $event)
    {
    }

    public function onRemove(LifecycleEventArgs $event)
    {
    }

    public function postPersist(LifecycleEventArgs $event)
    {
        $this->dispatchEvents($event);
    }

    public function postUpdate(LifecycleEventArgs $event)
    {
        $this->dispatchEvents($event);
    }

    public function postRemove(LifecycleEventArgs $event)
    {
        $this->dispatchEvents($event);
    }
}
