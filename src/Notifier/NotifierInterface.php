<?php

namespace Base\Notifier;

use Base\Entity\User\Notification;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

interface NotifierInterface extends \Symfony\Component\Notifier\NotifierInterface
{
    public function sendUsers(Notification $notification, RecipientInterface ...$recipients);
    public function sendUsersBy(array $channels, Notification $notification, ...$recipients);
    public function sendAdmins(Notification $notification);

    public function getTestRecipients(): array;
    public function getAdminRecipients(): array;
    public function getTechnicalRecipient(): ?RecipientInterface;

    public function isTest(RecipientInterface $recipient): bool;
    public function getPolicy(): ChannelPolicyInterface;
    public function getOptions(): array;
    public function getTranslator(): TranslatorInterface;

    public function enable();
    public function disable();

}