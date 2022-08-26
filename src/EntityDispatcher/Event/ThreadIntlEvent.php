<?php

namespace Base\EntityDispatcher\Event;

use Base\Entity\ThreadIntl;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The order.placed event is dispatched each time an order is created
 * in the system.
 */
class ThreadIntlEvent extends Event
{
    public const CLEANUP     = 'thread_intl.cleanup';

    public function __construct(ThreadIntl $threadIntl)
    {
        $this->threadIntl = $threadIntl;
    }

    protected $threadIntl;
    public function getThreadIntl(): ThreadIntl { return $this->threadIntl; }
}
