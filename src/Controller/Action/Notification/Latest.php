<?php

namespace Base\Controller\Action\Notification;

use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Notifier\NotificationLinkerInterface;
use Base\Repository\User\Notifications;
use Base\Screen\Cache;
use Base\Screen\Json;
use Omnibridge\Contract\Access;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The bell's payload, written once for every host (step R27 of the audit
 * "lesringostars face au master plan"; formerly NotificationController::
 * Latest(), and the closure of lesringostars-laravel's routes/web.php): the
 * signed-in account's unread count and latest entries (?limit=, 1 to 20,
 * 8 by default), for a tab open a while - the page's own dropdown is only
 * as fresh as the page; 401 for a visitor. Private to the account.
 *
 * A host's routes read its #[Route]: Symfony's router (under src/Controller, which an
 * omnibase site's routes import, @BaseBundle/Controller), omnibridge/laravel's
 * Routing\ActionRoutes (this folder).
 */
#[Route('/notifications/latest', name: 'user_notifications_latest', methods: ['GET'])]
final class Latest
{
    public function __construct(
        private readonly Access $access,
        private readonly Notifications $notifications,
        private readonly ?NotificationLinkerInterface $linker = null,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function __invoke(array $query = []): Json
    {
        $user = $this->access->getUser();
        if (!$user instanceof User) {
            return new Json(['unread' => 0, 'items' => []], 401, Cache::private());
        }

        $limit = min(20, max(1, \is_scalar($query['limit'] ?? null) ? (int) $query['limit'] : 8));
        $items = array_map($this->item(...), $this->notifications->findVisibleFor($user, $limit));

        return new Json(['unread' => $this->notifications->countUnreadFor($user), 'items' => $items], cache: Cache::private());
    }

    /** @return array<string, mixed> a notification as the bell's script reads it */
    public function item(Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'title' => $notification->getTitle() ?: $notification->getSubject(),
            'content' => strip_tags((string) $notification->getContent()),
            'url' => $notification->getUrl(),
            // The back-office page for the same target, when an admin is installed and the notification
            // knows what it is about; the client picks it in the back-office (data-notifications-context).
            'adminUrl' => $this->linker?->adminUrl($notification),
            'importance' => $notification->getImportance(),
            'isRead' => $notification->isRead(),
            'sentAt' => $notification->getSentAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
