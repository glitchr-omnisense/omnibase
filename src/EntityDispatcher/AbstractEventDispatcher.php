<?php

namespace Base\EntityDispatcher;

use Base\Database\Entity\EntityHydratorInterface;
use Base\Database\UnitOfWorks;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Exception;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Service\ResetInterface;
use Omnibridge\Persistence\UnitOfWork;

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
    /** What the listeners changed is flushed through it (plan "omnibase agnostic", F5a). */
    protected UnitOfWork $unitOfWork;

    /** @deprecated since omnisense/omnibase 4.x, removed at 6.0: $unitOfWork (null when a unit of work that is not Doctrine's was given) */
    protected ?EntityManagerInterface $entityManager;

    /**
     * @var RequestStack
     */
    protected RequestStack $requestStack;

    /**
     * @var PropertyAccessorInterface
     */
    protected PropertyAccessorInterface $propertyAccessor;

    /** @param UnitOfWork|EntityManagerInterface $unitOfWork the entity manager is deprecated (until 6.0): adapted by UnitOfWorks */
    public function __construct(SymfonyEventDispatcherInterface $dispatcher, EntityHydratorInterface $entityHydrator, UnitOfWork|EntityManagerInterface $unitOfWork, RequestStack $requestStack)
    {
        self::$live ??= new \WeakMap();
        self::$live[$this] = true;
        $this->dispatcher = $dispatcher;
        $this->unitOfWork = UnitOfWorks::of($unitOfWork);
        $this->entityManager = $unitOfWork instanceof EntityManagerInterface ? $unitOfWork : (method_exists($this->unitOfWork, 'entityManager') ? $this->unitOfWork->entityManager() : null);
        $this->entityHydrator = $entityHydrator;

        $this->requestStack = $requestStack;
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

    public function dispatchEvents(LifecycleEventArgs $event)
    {
        $object = $event->getObject();
        if ($object == null) {
            return;
        }

        $id = spl_object_id($object);
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
            $this->flushPending = true;
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
            if (null !== $this->entityManager && !$this->entityManager->isOpen()) {
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

    /**
     * @deprecated since omnisense/omnibase 4.x, removed at 6.0: getUnitOfWork()
     *
     * @return EntityManagerInterface|null
     */
    public function getEntityManager()
    {
        @trigger_error(sprintf('Since omnisense/omnibase 4.x: %s::getEntityManager() is deprecated, use getUnitOfWork().', static::class), E_USER_DEPRECATED);

        return $this->entityManager;
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

    public function prePersist(LifecycleEventArgs $event)
    {
        $object = $event->getObject();
        if ($object == null || !$this->supports($object)) {
            return;
        }

        $this->onPersist($event);
    }

    public function preUpdate(LifecycleEventArgs $event)
    {
        $object = $event->getObject();
        if ($object == null || !$this->supports($object)) {
            return;
        }

        $this->onUpdate($event);
    }

    public function preRemove(LifecycleEventArgs $event)
    {
        $object = $event->getObject();
        if ($object == null || !$this->supports($object)) {
            return;
        }

        $this->onRemove($event);
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

    public function onPersist(LifecycleEventArgs $event)
    {
    }

    public function onUpdate(LifecycleEventArgs $event)
    {
    }

    public function onRemove(LifecycleEventArgs $event)
    {
    }
}
