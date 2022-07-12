<?php

namespace Base\EntityDispatcher\Event;

use Base\Entity\ThreadTranslation;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The order.placed event is dispatched each time an order is created
 * in the system.
 */
class ThreadTranslationEvent extends Event
{
    public const CLEANUP     = 'thread_translation.cleanup';

    public function __construct(ThreadTranslation $threadTranslation)
    {
        $this->threadTranslation = $threadTranslation;
    }

    protected $threadTranslation;
    public function getThreadTranslation(): ThreadTranslation { return $this->threadTranslation; }
}
