<?php

namespace Base\Notifier\Channel;

use App\Entity\User;
use Base\Entity\User\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * The "inapp" channel: keeps a copy of the notification in the recipient's
 * notification list (userNotification), which is what the bell in the
 * toolbar and the back-office reads. Nothing else in the notifier persists
 * a Notification - it is built, sent over its channels and dropped - so
 * this channel is the one place a notification becomes a row.
 *
 * One row per user: the notifier calls a channel once per recipient with
 * the same Notification object, so the object itself cannot be the row (it
 * has a single user). A detached copy is stored instead, carrying only what
 * the list needs. Recipients that are not users (anonymous newsletter
 * subscribers) have no list to write to and are skipped.
 */
class InAppChannel implements ChannelInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(SymfonyNotification $notification, RecipientInterface $recipient): bool
    {
        return $notification instanceof Notification && $this->userOf($recipient) !== null;
    }

    public function notify(SymfonyNotification $notification, RecipientInterface $recipient, ?string $transportName = null): void
    {
        if (!$notification instanceof Notification) {
            return;
        }
        $user = $this->userOf($recipient);
        if (!$user) {
            return;
        }

        // A clone skips the constructor (which captures a backtrace and
        // resolves the notifier) and keeps the rendered texts as they are.
        $row = clone $notification;
        $row->setUser($user);
        $row->setChannels(["inapp"]);
        $row->setIsRead(false);
        $row->setSentAt(new \DateTime());

        $this->entityManager->persist($row);
        $this->entityManager->flush();
    }

    public function userOf(RecipientInterface $recipient): ?User
    {
        if (!$recipient instanceof EmailRecipientInterface || !$recipient->getEmail()) {
            return null;
        }

        try {
            $email = Address::create($recipient->getEmail())->getAddress();
        } catch (\Throwable) {
            return null;
        }

        return $this->entityManager->getRepository(User::class)->findOneBy(["email" => $email]);
    }
}
