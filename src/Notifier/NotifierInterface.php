<?php

namespace Base\Notifier;

use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Notifier\Abstract\BaseNotifierInterface;

interface NotifierInterface extends BaseNotifierInterface
{
    public function testEmail(User $user): Notification;
}
