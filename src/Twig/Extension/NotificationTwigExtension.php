<?php

namespace Base\Twig\Extension;

use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Repository\User\NotificationRepository;
use Base\Service\Push\WebPushService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * What a toolbar needs to draw the bell: the unread count (the red dot), the
 * latest entries (the dropdown), and the two things its script needs - the
 * CSRF token every mutation carries and whether push can be offered at all.
 * All of it is for the CURRENT user; anonymous visitors get zeros so the
 * templates need no guards of their own.
 */
class NotificationTwigExtension extends AbstractExtension
{
    public function __construct(
        protected readonly Security $security,
        protected readonly NotificationRepository $repository,
        protected readonly WebPushService $webPush,
        protected readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notifications_unread', [$this, 'unread']),
            new TwigFunction('notifications_latest', [$this, 'latest']),
            new TwigFunction('notifications_token', [$this, 'token']),
            new TwigFunction('push_available', [$this, 'pushAvailable']),
            new TwigFunction('push_vapid_key', [$this, 'pushKey']),
        ];
    }

    public function unread(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }
        return $this->repository->countUnreadFor($user);
    }

    /** @return Notification[] */
    public function latest(int $limit = 8): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }
        return $this->repository->findBy(["user" => $user], ["sentAt" => "DESC", "id" => "DESC"], $limit);
    }

    public function token(): string
    {
        return $this->csrfTokenManager->getToken("notifications")->getValue();
    }

    public function pushKey(): string
    {
        return (string) $this->webPush->getPublicKey();
    }

    public function pushAvailable(): bool
    {
        return $this->webPush->isConfigured() && $this->security->getUser() instanceof User;
    }
}
