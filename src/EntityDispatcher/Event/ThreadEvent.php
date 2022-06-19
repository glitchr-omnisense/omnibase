<?php

namespace Base\EntityDispatcher\Event;

use Base\Entity\Thread;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The order.placed event is dispatched each time an order is created
 * in the system.
 */
class ThreadEvent extends Event
{
    public const SCHEDULED   = 'thread.scheduled';
    public const PUBLISHABLE = 'thread.publishable';
    public const PUBLISHED   = 'thread.published';

    public function __construct(Thread $thread)
    {
        $this->thread = $thread;
    }

    protected $thread;
    public function getThread(): Thread { return $this->thread; }
}
