<?php

namespace Base\EntityDispatcher;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Omnibridge\Event\NamedEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * NB: Flushing object manager from within listener should be avoided, this is done already in event dispatcher if needed.
 *
 * A NamedEvent (plan "omnibase agnostic", F5c): its dispatcher gives it the name it is raised
 * under (user.register...), which a host's PSR-14 dispatcher listening by name reads.
 * The request is the host's own object (Symfony's, or Laravel's, a subclass of it).
 */
abstract class AbstractEvent extends Event implements EventInterface, NamedEvent
{
    protected LifecycleEventArgs $eventArgs;
    protected ?Request $request;
    protected ?string $eventName = null;

    public function __construct(LifecycleEventArgs $eventArgs, ?object $request)
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

    public function getLifecycle(): LifecycleEventArgs
    {
        return $this->eventArgs;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * @return ObjectManager
     */
    public function getObjectManager()
    {
        return $this->eventArgs->getObjectManager();
    }

    public function getObjectClass(): string
    {
        return get_class($this->eventArgs->getObject());
    }

    public function getObject(): object
    {
        return $this->eventArgs->getObject();
    }
}
