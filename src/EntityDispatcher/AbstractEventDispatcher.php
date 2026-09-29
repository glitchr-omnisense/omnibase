<?php

namespace Base\EntityDispatcher;

use Base\Database\Entity\EntityHydratorInterface;
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

    /**
     * @var PropertyAccessorInterface
     */
    protected PropertyAccessorInterface $propertyAccessor;

    public function __construct(SymfonyEventDispatcherInterface $dispatcher, EntityHydratorInterface $entityHydrator, EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->dispatcher = $dispatcher;
        $this->entityManager = $entityManager;
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
            if (!$this->entityManager->isOpen()) {
                $this->flushPending = false;

                return;
            }

            $this->flushPending = false;
            $this->flushing = true;
            try {
                $this->entityManager->flush();
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
     * @return EntityManagerInterface
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    public function getAssociationChangeSet($entity): array
    {
        $classMetadata = $this->entityManager->getClassMetadata(is_object($entity) ? get_class($entity) : $entity);
        if(!$classMetadata) return [];

        $changeSet = [];
        foreach($classMetadata->getAssociationNames() as $associationName) {

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
