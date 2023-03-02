<?php

namespace Base\Notifier\Abstract;

use Base\Entity\User\Notification;
use Base\Notifier\Recipient\Recipient;
use Base\Service\BaseService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Twig\Environment;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;

interface BaseNotificationInterface
{
    public function __toPrune(?RecipientInterface $recipient = null): SymfonyNotification;
    public function render(): Response;
    public function send(string $importance, RecipientInterface ...$recipients);
}