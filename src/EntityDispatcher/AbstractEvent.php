<?php

namespace Base\EntityDispatcher;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractEvent extends Event implements EventInterface
{
    protected $eventArgs;
    protected $requestStack;
    public function __construct(LifecycleEventArgs $eventArgs, Request $request)
    {
        $this->eventArgs = $eventArgs;
        $this->request   = $request;
    }

    public function getLifecycle(): LifecycleEventArgs { return $this->eventArgs; }
    public function getRequest(): Request { return $this->request; }

    public function getObjectManager()         { return $this->eventArgs->getObjectManager(); }
    public function getObjectClass()  : string { return get_class($this->eventArgs->getObject()); }
    public function getObject()       : object { return $this->eventArgs->getObject(); }
}
