<?php

declare(strict_types=1);

namespace Base\EntityDispatcher;

use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 *
 */
interface EventDispatcherInterface
{
    public function supports(mixed $subject): bool;

    /**
     * @param string $event
     * @param mixed $subject
     */
    public function addEvent(string $event, mixed $subject);

    /**
     * @param LifecycleEventArgs $event
     */
    public function dispatchEvents(LifecycleEventArgs $event);

    /**
     * @param LifecycleEventArgs $event
     */
    public function onPersist(LifecycleEventArgs $event);

    /**
     * @param LifecycleEventArgs $event
     */
    public function onUpdate(LifecycleEventArgs $event);

    /**
     * @param LifecycleEventArgs $event
     */
    public function onRemove(LifecycleEventArgs $event);
}
