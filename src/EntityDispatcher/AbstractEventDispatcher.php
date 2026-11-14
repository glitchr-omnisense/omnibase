<?php

namespace Base\EntityDispatcher;

use Base\Database\Entity\EntityHydratorInterface;
use Base\Database\UnitOfWorks;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Exception;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Omnibridge\Contract\Requests;
use Omnibridge\Event\Events;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Service\ResetInterface;
use Omnibridge\Persistence\Lifecycle;
use Omnibridge\Persistence\UnitOfWork;

/**
 * An entity dispatcher notes events before a write (persisting(), updating(), removing(): addEvent()) and dispatches them
 * once the write is done, through any PSR-14 dispatcher (user.register, thread.published...).
 *
 * It is called at a persistence's moments in two ways (plan "omnibase agnostic", step B leftover (a)):
 *  - lifecycle(), with omnisense/omnibridge's Lifecycle events (Omnibridge\Persistence\Lifecycle\PrePersist...): what a
 *    persistence other than Doctrine calls (omnibridge/eloquent), so that no Doctrine package is needed there;
 *  - prePersist() ... postRemove(), with doctrine/persistence's LifecycleEventArgs: Doctrine's event manager, where the
 *    dispatchers are its listeners (omnibridge/symfony's doctrine.event_listener tags, omnibridge/laravel's).
 * Both reach the same hooks, persisting(PrePersist), updating(PreUpdate), removing(PreRemove). The former hooks typed on
 * doctrine/persistence (onPersist(), onUpdate(), onRemove(), dispatchEvents()) are gone since 6.0: a dispatcher that still
 * declares one is never called through it (UPGRADE-6.0.md: persisting(), updating(), removing()).
 */
abstract class AbstractEventDispatcher implements EventDispatcherInterface, ResetInterface
{
    protected array $events;

    /**
     * Listeners of the events dispatched after a write may change entities.
     * Those changes are flushed once the flush that raised the events is
     * completely over - by flushPending(), when the response goes out, a
     * command ends, a message is handled (EntityDispatcherFlushSubscriber) -
     * and never from inside it:
     *  - not in postPersist/postUpdate: Doctrine forbids it, and it wrote the
     *    second-level cache of entities whose uploads were not stored yet;
     *  - not in postFlush either: Doctrine fires it before it clears the
     *    flush's schedules, so a flush there ran the outer flush's collection
     *    deletions a second time, with nothing to insert back - a form that
     *    rewrote a ManyToMany (a thread's tags) lost its rows.
     * A flush the application makes later in the request takes them along.
     */
    protected bool $flushPending = false;
    protected bool $flushing = false;

    /**
     * The dispatchers built in this process (a weak map: a dispatcher gone is
     * forgotten): the only ones that can have something pending.
     *
     * @var \WeakMap<AbstractEventDispatcher, true>|null
     */
    private static ?\WeakMap $live = null;

    /** @return list<AbstractEventDispatcher> the dispatchers built in this process */
    public static function live(): array
    {
        $live = [];
        foreach (self::$live ?? [] as $dispatcher => $_) {
            $live[] = $dispatcher;
        }

        return $live;
    }

    /**
     * Any PSR-14 dispatcher (plan "omnibase agnostic", F5c): Symfony's is given the event's name
     * (user.register...), any other the event alone, which carries it (Omnibridge\Event\NamedEvent).
     */
    protected PsrEventDispatcherInterface $dispatcher;

    /**
     * @var EntityHydratorInterface
     */
    protected $entityHydrator;

    /** What the listeners changed is flushed through it (plan "omnibase agnostic", F5a). */
    protected UnitOfWork $unitOfWork;

    /** The request being answered, which an event carries (null in a command, a worker, plain PHP). */
    protected ?Requests $requests;

    /**
     * @var PropertyAccessorInterface
     */
    protected PropertyAccessorInterface $propertyAccessor;

    public function __construct(PsrEventDispatcherInterface $dispatcher, EntityHydratorInterface $entityHydrator, UnitOfWork $unitOfWork, #[Autowire(service: Requests::class)] ?Requests $requests = null)
    {
        self::$live ??= new \WeakMap();
        self::$live[$this] = true;
        $this->dispatcher = $dispatcher;
        $this->unitOfWork = $unitOfWork;
        $this->entityHydrator = $entityHydrator;

        $this->requests = $requests;
        $this->events = [];

        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public const DISPATCHER_SUFFIX = "Dispatcher";

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

    public function addEvent(string $event, mixed $object)
    {
        $id = spl_object_id($object);
        if (!array_key_exists($id, $this->events)) {
            $this->events[$id] = [];
        }

        if (!array_key_exists($event, $this->events[$id])) {
            $this->events[$id][$event] = true;
        }
    }

    /**
     * The events noted for an entity, dispatched once; each carries the persistence's own arguments of the moment
     * (omnisense/omnibridge's Lifecycle event, or Doctrine's LifecycleEventArgs under Doctrine's event manager).
     */
    private function dispatchFor(?object $object, LifecycleEventArgs|Lifecycle\EntityEvent $eventArgs): void
    {
        if ($object == null) {
            return;
        }

        $id = spl_object_id($object);
        if (!array_key_exists($id, $this->events)) {
            return;
        }

        $reflush = false;
        $eventClass = $this->getEventClass();

        $request = $this->requests?->request();
        foreach ($this->events[$id] as $eventName => $alreadyTriggered) {

            if ($alreadyTriggered === false) {
                continue;
            }

            $this->events[$id][$eventName] = false;
            $dispatched = new $eventClass($eventArgs, $request);
            if ($dispatched instanceof AbstractEvent) {
                $dispatched->setEventName($eventName);
            }
            Events::dispatch($this->dispatcher, $dispatched, $eventName);
            $reflush = true;
        }

        if ($reflush) {
            $this->flushPending = true;
        }
    }

    /**
     * A persistence's moment, as omnisense/omnibridge's Lifecycle event (plan "omnibase agnostic", step B leftover (a)):
     * before a write the dispatcher notes its events (persisting(), updating(), removing(), for an entity it supports),
     * after it dispatches them. The other moments (a flush, a load) are not the dispatchers'. Nothing of doctrine/persistence
     * is needed.
     */
    public function lifecycle(Lifecycle\EntityEvent $event): void
    {
        $entity = $event->entity;
        if ($event instanceof Lifecycle\PrePersist) {
            if ($this->supports($entity)) {
                $this->persisting($event);
            }
        } elseif ($event instanceof Lifecycle\PreUpdate) {
            if ($this->supports($entity)) {
                $this->updating($event);
            }
        } elseif ($event instanceof Lifecycle\PreRemove) {
            if ($this->supports($entity)) {
                $this->removing($event);
            }
        } elseif ($event instanceof Lifecycle\PostPersist || $event instanceof Lifecycle\PostUpdate || $event instanceof Lifecycle\PostRemove) {
            $this->dispatchFor($entity, $event);
        }
    }

    /** Nothing is flushed here any more: see $flushPending. */
    public function postFlush(PostFlushEventArgs $event): void
    {
    }

    /**
     * What the listeners changed, flushed - outside any flush. A flush may
     * raise events again, whose listeners change more: a few rounds at most.
     */
    public function flushPending(): void
    {
        for ($round = 0; $this->flushPending && !$this->flushing && $round < 5; ++$round) {
            // A closed entity manager (an exception during a flush) cannot flush again.
            $entityManager = method_exists($this->unitOfWork, 'entityManager') ? $this->unitOfWork->entityManager() : null;
            if (null !== $entityManager && method_exists($entityManager, 'isOpen') && !$entityManager->isOpen()) {
                $this->flushPending = false;

                return;
            }

            $this->flushPending = false;
            $this->flushing = true;
            try {
                $this->unitOfWork->flush();
            } finally {
                $this->flushing = false;
            }
        }
    }

    /**
     * Keyed by spl_object_id, which PHP gives again to a new object once the
     * old one is gone: in a worker, a message's entity inherited the events
     * (or the "already sent" mark) of an earlier one's.
     */
    public function reset(): void
    {
        $this->events = [];
        $this->flushPending = false;
    }

    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    public function getAssociationChangeSet($entity): array
    {
        $shapes = UnitOfWorks::shapes($this->unitOfWork);
        $class = is_object($entity) ? $this->unitOfWork->className($entity) : $entity;
        if(!$shapes->has($class)) return [];
        $shape = $shapes->shape($class);

        $changeSet = [];
        foreach($shape->associationNames() as $associationName) {

            if(!empty($this->getAssociationDeleteDiff($entity, $associationName))) $changeSet[] = $associationName;
            else if(!empty($this->getAssociationInsertDiff($entity, $associationName))) $changeSet[] = $associationName;
        }

        return $changeSet;
    }

    public function getAssociationDeleteDiff($entity, $field): array
    {
        $collection = $this->propertyAccessor->getValue($entity, $field);
        if(!$collection instanceof PersistentCollection) return [];
        if(!$collection->isDirty()) return [];

        return $collection->getOwner()->getDeleteDiff();
    }

    public function getAssociationInsertDiff($entity, $field): array
    {
        $collection = $this->propertyAccessor->getValue($entity, $field);
        if(!$collection instanceof PersistentCollection) return [];
        if(!$collection->isDirty()) return [];

        return $collection->getOwner()->getInsertDiff();
    }

    /** Doctrine's event manager's prePersist (any other caller: lifecycle()). */
    public function prePersist(LifecycleEventArgs $event)
    {
        $object = $event->getObject();
        if ($object == null || !$this->supports($object)) {
            return;
        }

        $this->persisting(new Lifecycle\PrePersist($object, $this->unitOfWork));
    }

    /** Doctrine's event manager's preUpdate, its changes (PreUpdateEventArgs) given to updating(). */
    public function preUpdate(LifecycleEventArgs $event)
    {
        $object = $event->getObject();
        if ($object == null || !$this->supports($object)) {
            return;
        }

        $this->updating(new Lifecycle\PreUpdate($object, $this->unitOfWork, method_exists($event, 'getEntityChangeSet') ? $event->getEntityChangeSet() : []));
    }

    /** Doctrine's event manager's preRemove. */
    public function preRemove(LifecycleEventArgs $event)
    {
        $object = $event->getObject();
        if ($object == null || !$this->supports($object)) {
            return;
        }

        $this->removing(new Lifecycle\PreRemove($object, $this->unitOfWork));
    }

    public function postPersist(LifecycleEventArgs $event)
    {
        $this->dispatchFor($event->getObject(), $event);
    }

    public function postUpdate(LifecycleEventArgs $event)
    {
        $this->dispatchFor($event->getObject(), $event);
    }

    public function postRemove(LifecycleEventArgs $event)
    {
        $this->dispatchFor($event->getObject(), $event);
    }

    /** Before a supported entity is inserted: the events to dispatch once it is (addEvent()). */
    public function persisting(Lifecycle\PrePersist $event): void
    {
    }

    /** Before a supported entity is updated, its changes in the event (the original entity: EntityHydrator::getOriginalEntity($event)). */
    public function updating(Lifecycle\PreUpdate $event): void
    {
    }

    /** Before a supported entity is removed. */
    public function removing(Lifecycle\PreRemove $event): void
    {
    }
}
