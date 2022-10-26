<?php

namespace Base\EntityDispatcher;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractEvent extends Event implements EventInterface
{
    protected $eventArgs;
    public function __construct(LifecycleEventArgs $eventArgs) 
    {
        $this->eventArgs = $eventArgs;
    }

    public function getLifecycle(): LifecycleEventArgs { return $this->eventArgs; }

    public function getObjectManager()         { return $this->eventArgs->getObjectManager(); }
    public function getObjectClass()  : string { return get_class($this->eventArgs->getObject()); }
    public function getObject()       : object { return $this->eventArgs->getObject(); }
}
