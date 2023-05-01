<?php

namespace Base\Notifier\Abstract;

use Base\Entity\User\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Twig\Environment;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 *
 */
interface BaseNotifierInterface extends NotifierInterface
{
    /**
     * @param Notification $notification
     * @param RecipientInterface ...$recipients
     * @return mixed
     */
    public function sendUsers(Notification $notification, RecipientInterface ...$recipients);

    /**
     * @param array $channels
     * @param Notification $notification
     * @param RecipientInterface ...$recipients
     * @return mixed
     */
    public function sendUsersBy(array $channels, Notification $notification, RecipientInterface ...$recipients);

    /**
     * @param Notification $notification
     * @return mixed
     */
    public function sendAdmins(Notification $notification);

    public function getTestRecipients(): array;

    public function getAdminRecipients(): array;

    public function getTechnicalRecipient(): ?RecipientInterface;

    public function hasLoopback(): bool;

    public function isTest(RecipientInterface $recipient): bool;

    public function getPolicy(): ChannelPolicyInterface;

    public function getOptions(): array;

    public function getTranslator(): TranslatorInterface;

    public function getEnvironment(): Environment;

    /**
     * @return mixed
     */
    public function enable();

    /**
     * @return mixed
     */
    public function disable();
}
