<?php

namespace Base\Controller\Action\Settings;

use Base\Entity\User\Notification;

/**
 * How an account action written once for every host tells the account what
 * it did (step R29): the host's own notice where it gives one
 * (omnibridge/laravel: its flash, in the visitor's language), else omnibase's
 * Notifier (Notification::send(), a flash under Symfony).
 */
final class Notice
{
    /** @param (\Closure(string $label, string $message): void)|null $notice */
    public static function send(?\Closure $notice, string $label, string $message): void
    {
        if (null !== $notice) {
            $notice($label, $message);

            return;
        }
        (new Notification($message))->send($label);
    }
}
