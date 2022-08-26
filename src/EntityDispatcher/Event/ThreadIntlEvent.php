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
    public const CLEANUP     = 'thread_translation.cleanup';

    public function __construct(ThreadIntl $threadTranslation)
    {
        $this->threadIntl = $threadTranslation;
    }

    protected $threadTranslation;
    public function getThreadIntl(): ThreadIntl { return $this->threadTranslation; }
}
