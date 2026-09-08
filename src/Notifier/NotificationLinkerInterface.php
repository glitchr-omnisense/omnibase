<?php

namespace Base\Notifier;

use Base\Entity\User\Notification;

/**
 * Turns a notification's target (class + id, see Notification::setTarget())
 * into a back-office URL. base-bundle ships a null implementation; the
 * admin bundle replaces it with one that knows its CRUD routes, so a
 * notification opened from the back-office lands on the record's edit page
 * while the same notification on the front-end keeps its public URL.
 */
interface NotificationLinkerInterface
{
    public function adminUrl(Notification $notification): ?string;
}
