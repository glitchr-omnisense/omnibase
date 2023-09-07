<?php

namespace Base\EntityDispatcher;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * NB: Flushing object manager from within listener should be avoided, this is done already in event dispatcher if needed.
 */
abstract class AbstractEvent extends Event implements EventInterface
{
    protected LifecycleEventArgs $eventArgs;
    protected ?Request $request;

    public function __construct(LifecycleEventArgs $eventArgs, ?Request $request)
    {
        $this->eventArgs = $eventArgs;
        $this->request = $request;
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
