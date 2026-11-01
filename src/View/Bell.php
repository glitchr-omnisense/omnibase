<?php

namespace Base\View;

use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Notifier\NotificationLinkerInterface;
use Base\Repository\User\Notifications;
use Base\Service\Push\WebPushService;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;

/**
 * What a toolbar needs to draw the bell (plan "omnibase agnostic", step F4): the unread count (the
 * red dot), the latest entries (the dropdown), the CSRF token every mutation carries, whether push
 * can be offered and its public key, the back-office page of a notification. All of it for the
 * user signed in (the host's Access contract); anonymous visitors get zeros, so a view needs no
 * guard of its own. NotificationTwigExtension's notifications_*() and push_*() under Twig, the
 * same code for Blade and a React page's props; push is offered where WebPushService is
 * configured (none given: never).
 */
final class Bell
{
    public function __construct(
        private readonly Access $access,
        private readonly Notifications $repository,
        private readonly ?WebPushService $webPush,
        private readonly Tokens $tokens,
        private readonly ?NotificationLinkerInterface $linker = null,
    ) {
    }

    public function unread(): int
    {
        $user = $this->access->getUser();

        return $user instanceof User ? $this->repository->countUnreadFor($user) : 0;
    }

    /** @return Notification[] */
    public function latest(int $limit = 8): array
    {
        $user = $this->access->getUser();

        return $user instanceof User ? $this->repository->findVisibleFor($user, $limit) : [];
    }

    public function token(): string
    {
        return $this->tokens->csrf('notifications');
    }

    public function pushKey(): string
    {
        return (string) $this->webPush?->getPublicKey();
    }

    /** The back-office page of what a notification is about, or null. */
    public function adminUrl(Notification $notification): ?string
    {
        return $this->linker?->adminUrl($notification);
    }

    public function pushAvailable(): bool
    {
        return (bool) $this->webPush?->isConfigured() && $this->access->getUser() instanceof User;
    }
}
