<?php

namespace Base\EntityDispatcher\Event;

use Base\Entity\Thread;
use Base\EntityDispatcher\AbstractEvent;

class ThreadEvent extends AbstractEvent
{
    public const SCHEDULED   = 'thread.scheduled';
    public const PUBLISHABLE = 'thread.publishable';
    public const PUBLISHED   = 'thread.published';

    public function getThread(): Thread { return $this->getObject(); }
}
