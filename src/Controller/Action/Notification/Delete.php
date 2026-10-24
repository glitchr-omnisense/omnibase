<?php

namespace Base\Controller\Action\Notification;

use Base\Repository\User\Notifications;
use Base\Screen\Cache;
use Base\Screen\Json;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The bell's removals, written once for every host (step R29 of the audit
 * "lesringostars face au master plan"; formerly NotificationController::
 * Delete()). Body: {token, id?} - one notification of the account, or every
 * read one when id is absent. The token is checked as Read checks it; 403
 * without it or for a visitor.
 */
#[Route('/notifications/delete', name: 'user_notifications_delete', methods: ['POST'])]
final class Delete
{
    public function __construct(
        private readonly Access $access,
        private readonly Tokens $tokens,
        private readonly Notifications $notifications,
    ) {
    }

    /** @param array<string, mixed> $body */
    public function __invoke(array $body = []): Json
    {
        $user = Read::account($this->access, $this->tokens, $body);
        if (null === $user) {
            return new Json(['success' => false], 403, Cache::private());
        }

        $deleted = [];
        $removed = !empty($body['id']) && \is_scalar($body['id'])
            ? array_filter([$this->notifications->findOneBy(['id' => (int) $body['id'], 'user' => $user])])
            : $this->notifications->findBy(['user' => $user, 'isRead' => true]);
        foreach ($removed as $notification) {
            $deleted[] = $notification->getId();
            $this->notifications->remove($notification);
        }
        $this->notifications->flush();

        return new Json(['success' => true, 'deleted' => $deleted, 'unread' => $this->notifications->countUnreadFor($user)], cache: Cache::private());
    }
}
