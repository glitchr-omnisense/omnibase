<?php

namespace Base\Notifier\Abstract;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;

/**
 *
 */
interface BaseNotificationInterface
{
    public function __toPrune(?RecipientInterface $recipient = null): SymfonyNotification;

    public function render(): Response;

    public function send(string $importance, RecipientInterface ...$recipients);
}
