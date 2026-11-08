<?php

namespace Base\EntityDispatcher;

use Base\Database\UnitOfWorks;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Omnibridge\Event\NamedEvent;
use Omnibridge\Persistence\Lifecycle\EntityEvent;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * NB: Flushing object manager from within listener should be avoided, this is done already in event dispatcher if needed.
 *
 * A NamedEvent (plan "omnibase agnostic", F5c): its dispatcher gives it the name it is raised
 * under (user.register...), which a host's PSR-14 dispatcher listening by name reads.
 * The request is the host's own object (Symfony's, or Laravel's, a subclass of it).
 *
 * It carries the persistence's arguments of the moment it was dispatched at (plan "omnibase agnostic", step B leftover
 * (a)): omnisense/omnibridge's Lifecycle event (AbstractEventDispatcher::lifecycle(), any persistence), or Doctrine's
 * LifecycleEventArgs where Doctrine's event manager called the dispatcher. getObject() and getUnitOfWork() read either.
 */
abstract class AbstractEvent extends Event implements EventInterface, NamedEvent
{
    protected LifecycleEventArgs|EntityEvent $eventArgs;
    protected ?Request $request;
    protected ?string $eventName = null;

    public function __construct(LifecycleEventArgs|EntityEvent $eventArgs, ?object $request)
    {
        $this->eventArgs = $eventArgs;
        $this->request = $request instanceof Request ? $request : null;
    }

    public function setEventName(string $eventName): static
    {
        $this->eventName = $eventName;

        return $this;
    }

    public function eventName(): string
    {
        return $this->eventName ?? static::class;
    }

    /** The persistence's arguments of the moment: omnisense/omnibridge's Lifecycle event, or Doctrine's LifecycleEventArgs. */
    public function getLifecycle(): LifecycleEventArgs|EntityEvent
    {
        return $this->eventArgs;
    }

    /** The unit of work the entity was written through, whichever persistence. */
    public function getUnitOfWork(): UnitOfWork
    {
        return $this->eventArgs instanceof EntityEvent ? $this->eventArgs->unitOfWork : UnitOfWorks::of($this->eventArgs->getObjectManager());
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * Doctrine's object manager under Doctrine's event manager; the unit of work otherwise.
     *
     * @deprecated since omnisense/omnibase 5.x, removed at 6.0: getUnitOfWork()
     *
     * @return ObjectManager|UnitOfWork
     */
    public function getObjectManager()
    {
        return $this->eventArgs instanceof EntityEvent ? $this->eventArgs->unitOfWork : $this->eventArgs->getObjectManager();
    }

    public function getObjectClass(): string
    {
        return get_class($this->getObject());
    }

    public function getObject(): object
    {
        return $this->eventArgs instanceof EntityEvent ? $this->eventArgs->entity : $this->eventArgs->getObject();
    }
}
