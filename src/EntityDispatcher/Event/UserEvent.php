<?php

namespace Base\EntityDispatcher\Event;

use App\Entity\User;
use Base\EntityDispatcher\AbstractEvent;

/**
 * The order.placed event is dispatched each time an order is created
 * in the system.
 */
class UserEvent extends AbstractEvent
{
    public const REGISTER = 'user.register';
    public const VERIFIED = 'user.verified';
    public const APPROVAL = 'user.approval';
    public const DISABLED = 'user.disabled';
    public const ENABLED  = 'user.enabled' ;
    public const KICKED   = 'user.kickout' ;
    public const LOCKED   = 'user.locked'  ;
    public const NEWCOMER = 'user.newcomer';
    public const GHOST    = 'user.ghost'   ;
    public const BANNED   = 'user.banned'  ;

    public function getUser(): User
    {
        return $this->getObject();
    }
}
