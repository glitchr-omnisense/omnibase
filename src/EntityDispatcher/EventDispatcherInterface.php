<?php

declare(strict_types=1);

namespace Base\EntityDispatcher;

use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * An entity dispatcher (AbstractEventDispatcher). The methods typed on doctrine/persistence's LifecycleEventArgs are
 * deprecated since omnisense/omnibase 5.x and leave at 6.0: AbstractEventDispatcher's neutral hooks (persisting(),
 * updating(), removing()) and its entry lifecycle() need no Doctrine package (plan "omnibase agnostic", step B leftover (a)).
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
