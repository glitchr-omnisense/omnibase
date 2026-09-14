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

    /**
     * Always true, and that is the whole point of "silent".
     *
     * Symfony's Notifier does not skip a channel whose supports() returns false:
     * it throws LogicException('The "push" channel is not supported.') and
     * aborts the ENTIRE send. This used to return isConfigured() && a user, so on
     * a host without VAPID keys every "notify" send threw. In production that
     * killed thread:publishable on every run - the announcement runs inside the
     * cron's flush, the exception rolled the whole transaction back, and a
     * scheduled article stayed unpublished, retried and rolled back every 5
     * minutes. The "nothing to do" cases are handled in notify() instead.
     */
    public function supports(SymfonyNotification $notification, RecipientInterface $recipient): bool
    {
        return true;
    }

    public function notify(SymfonyNotification $notification, RecipientInterface $recipient, ?string $transportName = null): void
    {
        if (!$this->webPush->isConfigured()) {
            return;
        }

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
