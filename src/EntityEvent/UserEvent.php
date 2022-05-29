<?php

namespace Base\EntityEvent;

use Base\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The order.placed event is dispatched each time an order is created
 * in the system.
 */
class UserEvent extends Event
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

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}