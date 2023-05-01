<?php

declare(strict_types=1);

namespace Base\EntityDispatcher;

use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;

interface EventDispatcherInterface extends EventSubscriber
{
    public function supports(mixed $subject): bool;

    public function addEvent(string $event, mixed $subject);

    public function dispatchEvents(LifecycleEventArgs $event);

    public function onPersist(LifecycleEventArgs $event);

    public function onUpdate(LifecycleEventArgs $event);

    public function onRemove(LifecycleEventArgs $event);
}
