<?php

namespace Base\Notifier\Channel;

use Base\Entity\User\Notification;
use Base\Service\Push\WebPushService;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * The "push" channel: a Web Push message to every browser the recipient
 * enabled notifications in. Silent when VAPID keys are not configured or
 * the recipient is not a user, so a policy can list it unconditionally.
 */
class PushChannel implements ChannelInterface
{
    public function __construct(
        protected readonly WebPushService $webPush,
        protected readonly InAppChannel $inApp,
    ) {
    }

    public function supports(SymfonyNotification $notification, RecipientInterface $recipient): bool
    {
        return $this->webPush->isConfigured() && $this->inApp->userOf($recipient) !== null;
    }

    public function notify(SymfonyNotification $notification, RecipientInterface $recipient, ?string $transportName = null): void
    {
        $user = $this->inApp->userOf($recipient);
        if (!$user) {
            return;
        }

        $title = $notification->getSubject() ?: ($notification instanceof Notification ? $notification->getTitle() : "");
        $body = $notification->getContent();
        $url = $notification instanceof Notification ? $notification->getUrl() : null;

        $this->webPush->sendToUsers([$user], array_filter([
            "title" => strip_tags($title ?: $body),
            "body" => $title ? strip_tags($body) : "",
            "url" => $url,
            "tag" => $url ? "url:" . md5($url) : null,
        ], fn ($v) => $v !== null));
    }
}
