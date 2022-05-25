<?php

namespace Base\EntityEvent;

use Base\Entity\Thread;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The order.placed event is dispatched each time an order is created
 * in the system.
 */
class ThreadEvent extends Event
{
    public const PUBLISH = 'thread.publish';

    protected $thread;

    public function __construct(Thread $thread)
    {
        $this->thread = $thread;
    }

    public function getThread(): Thread
    {
        return $this->thread;
    }
}
