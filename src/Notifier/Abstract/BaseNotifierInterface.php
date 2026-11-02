<?php

namespace Base\Notifier\Abstract;

use Base\Entity\User\Notification;
use Base\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

interface BaseNotifierInterface extends NotifierInterface
{
    /**
     * @param Notification $notification
     * @param RecipientInterface ...$recipients
     * @return mixed
     */
    public function sendUsers(Notification $notification, Recipient ...$recipients);

    /**
     * @param array $channels
     * @param Notification $notification
     * @param RecipientInterface ...$recipients
     * @return mixed
     */
    public function sendUsersBy(array $channels, Notification $notification, Recipient ...$recipients);

    /**
     * @param Notification $notification
     * @return mixed
     */
    public function sendAdmins(Notification $notification);

    public function getTestRecipients(): array;

    public function getAdminRecipients(): array;

    public function getTechnicalRecipient(): ?Recipient;

    public function hasLoopback(): bool;

    public function isTest(Recipient $recipient): bool;

    public function getPolicy(): ChannelPolicyInterface;

    public function getOptions(): array;

    public function getTranslator(): TranslatorInterface;

    /** The views the mails and pages render through (getEnvironment(), Twig's, until step F5a). */
    public function getViews(): \Omnibridge\Contract\Views;

    /**
     * @return mixed
     */
    public function enable();

    /**
     * @return mixed
     */
    public function disable();
}
