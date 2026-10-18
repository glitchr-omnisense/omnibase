<?php

namespace Base\Screen\Account;

use Base\Screen\Confidential;
use Base\Screen\Shape;

/**
 * The member's notifications (/notifications): the latest with something to
 * show (newest first; the unread ones alone when asked), how many are unread
 * (counted), whether the page shows the unread ones alone and the address of
 * all of them, the token every request of the page carries and the addresses
 * of those requests (read, latest, delete, push subscribe and unsubscribe),
 * whether push can be offered and its public key. Its token: confidential.
 */
final readonly class NotificationsPage implements Confidential
{
    /** @param list<Notification> $notifications */
    public function __construct(
        public array $notifications,
        public int $unread,
        public bool $unreadOnly,
        public string $allUrl,
        public string $token,
        public string $readUrl,
        public string $latestUrl,
        public string $deleteUrl,
        public string $subscribeUrl,
        public string $unsubscribeUrl,
        public bool $push = false,
        public ?string $pushKey = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'notifications' => Shape::export($this->notifications),
            'unread' => $this->unread,
            'unreadOnly' => $this->unreadOnly,
            'allUrl' => $this->allUrl,
            'token' => $this->token,
            'readUrl' => $this->readUrl,
            'latestUrl' => $this->latestUrl,
            'deleteUrl' => $this->deleteUrl,
            'subscribeUrl' => $this->subscribeUrl,
            'unsubscribeUrl' => $this->unsubscribeUrl,
            'push' => $this->push,
            'pushKey' => $this->pushKey,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
