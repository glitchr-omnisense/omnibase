<?php

namespace Base\Notifier;

use App\Entity\User;
use App\Entity\User\Notification;
use Base\Notifier\Abstract\BaseNotifierInterface;

/**
 *
 */
interface NotifierInterface extends BaseNotifierInterface
{
    public function testEmail(?User $user): Notification;
}
