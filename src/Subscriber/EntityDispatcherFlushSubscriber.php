<?php

namespace Base\Subscriber;

use Base\EntityDispatcher\AbstractEventDispatcher;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * Flushes what the entity dispatchers' listeners changed (a thread
 * published by its "publishable" event, a user's welcome state...), once the
 * flush that raised those events is completely over: when the response goes
 * out, when a command ends, after each message a worker handles. See
 * AbstractEventDispatcher::$flushPending for why not from postFlush.
 */
final class EntityDispatcherFlushSubscriber implements EventSubscriberInterface
{
    /** @param iterable<AbstractEventDispatcher> $dispatchers */
    public function __construct(private readonly iterable $dispatchers)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before the response's other listeners (SecuritySubscriber's
            // poke flushes too): their flush then finds nothing left over.
            KernelEvents::RESPONSE => ['onResponse', 64],
            ConsoleEvents::TERMINATE => ['flush', 64],
            WorkerMessageHandledEvent::class => ['flush', 64],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        foreach ($this->dispatchers as $dispatcher) {
            if ($dispatcher instanceof AbstractEventDispatcher) {
                $dispatcher->flushPending();
            }
        }
    }
}
