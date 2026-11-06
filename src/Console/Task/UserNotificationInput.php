<?php

namespace Base\Console\Task;

/** user:notifications' options (plan "omnibase agnostic", step F5e, slice 3). */
final readonly class UserNotificationInput
{
    /**
     * @param ?string $user     the account (its identifier); every account's without it
     * @param ?string $expiry   the age past which a read notification is erased ("+30 days"); base.user.notifications.expiry without it
     * @param bool    $show     list them
     * @param bool    $clear    erase the read ones older than the expiry
     * @param bool    $clearAll erase every read one, whatever its age
     */
    public function __construct(
        public ?string $user = null,
        public ?string $expiry = null,
        public bool $show = false,
        public bool $clear = false,
        public bool $clearAll = false,
    ) {
    }
}
