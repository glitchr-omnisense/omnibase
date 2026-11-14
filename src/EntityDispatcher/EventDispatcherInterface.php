<?php

declare(strict_types=1);

namespace Base\EntityDispatcher;

/**
 * An entity dispatcher (AbstractEventDispatcher). Since 6.0 it declares nothing typed on doctrine/persistence (5.x's
 * dispatchEvents(), onPersist(), onUpdate(), onRemove() are gone): AbstractEventDispatcher's neutral hooks (persisting(),
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
}
