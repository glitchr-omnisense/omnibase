<?php

namespace Base\EntityDispatcher\Event;

use App\Entity\User;
use Base\EntityDispatcher\AbstractEvent;

/**
 * The user.* events: UserEventDispatcher dispatches user.register when an account is
 * persisted, and each other one when an update puts the account in that state (approved,
 * verified, enabled, disabled, kicked out, locked, newcomer, banned; user.ghost when it stops
 * being a ghost).
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
