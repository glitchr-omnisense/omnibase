<?php

namespace Base\Twig\Extension;

use Base\Entity\User\Notification;
use Base\Notifier\NotificationLinkerInterface;
use Base\Repository\User\Notifications;
use Base\Service\Push\WebPushService;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Base\View\Bell;

/**
 * What a toolbar needs to draw the bell: the unread count (the red dot), the
 * latest entries (the dropdown), and the two things its script needs - the
 * CSRF token every mutation carries and whether push can be offered at all.
 * All of it is for the CURRENT user; anonymous visitors get zeros so the
 * templates need no guards of their own.
 *
 * Framework-free (audit "lesringostars face au master plan", R24): the user
 * signed in is the host's Access contract, the token its Tokens contract
 * (Symfony's security and CSRF manager under Symfony, the guard and the
 * session's token under Laravel); push is offered where WebPushService is
 * configured (none given: never).
 */
class NotificationTwigExtension extends AbstractExtension
{
    public function __construct(
        protected readonly Access $access,
        protected readonly Notifications $repository,
        protected readonly ?WebPushService $webPush,
        protected readonly Tokens $tokens,
        protected readonly ?NotificationLinkerInterface $linker = null,
    ) {
        $this->bell = new Bell($access, $repository, $webPush, $tokens, $linker);
    }

    private readonly Bell $bell;

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notifications_unread', [$this, 'unread']),
            new TwigFunction('notifications_latest', [$this, 'latest']),
            new TwigFunction('notifications_token', [$this, 'token']),
            new TwigFunction('push_available', [$this, 'pushAvailable']),
            new TwigFunction('notification_admin_url', [$this, 'adminUrl']),
            new TwigFunction('push_vapid_key', [$this, 'pushKey']),
        ];
    }

    public function unread(): int
    {
        return $this->bell->unread();
    }

    /** @return Notification[] */
    public function latest(int $limit = 8): array
    {
        return $this->bell->latest($limit);
    }

    public function token(): string
    {
        return $this->bell->token();
    }

    public function pushKey(): string
    {
        return $this->bell->pushKey();
    }

    /** The back-office page of what a notification is about, or null. */
    public function adminUrl(Notification $notification): ?string
    {
        return $this->bell->adminUrl($notification);
    }

    public function pushAvailable(): bool
    {
        return $this->bell->pushAvailable();
    }
}
