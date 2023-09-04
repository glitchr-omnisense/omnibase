<?php

namespace Base\EntityDispatcher;

use Base\Database\Entity\EntityHydratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Exception;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

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

        return $entity->getOwners()->getDeleteDiff();
    }

    public function getAssociationInsertDiff($entity, $field): array
    {
        $collection = $this->propertyAccessor->getValue($entity, $field);
        if(!$collection instanceof PersistentCollection) return [];
        if(!$collection->isDirty()) return [];

        return $entity->getOwners()->getInsertDiff();
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
        $this->getAssociationChangeSet($object);

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
