<?php

namespace Base\Notifier\Abstract;

use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;

interface BaseNotificationInterface
{
    public function __toPrune(?RecipientInterface $recipient = null): SymfonyNotification;

    /**
     * The notification as a page: Symfony's Response under Symfony (Base\Entity\User\Notification
     * gives Base\Bridge\Symfony\NotificationMessages::response()). Untyped since step C3f of the
     * plan "omnibase agnostique", so that the interface names nothing of the framework; an
     * implementation typed `: Response` stays compatible.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render();

    public function send(string $importance, RecipientInterface ...$recipients);
}
