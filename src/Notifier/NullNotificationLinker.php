<?php

namespace Base\Notifier;

use Base\Entity\User\Notification;

/** No back-office installed: there is no admin page to link to. */
final class NullNotificationLinker implements NotificationLinkerInterface
{
    public function adminUrl(Notification $notification): ?string
    {
        return null;
    }
}
