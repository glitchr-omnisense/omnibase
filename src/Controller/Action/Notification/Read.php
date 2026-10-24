<?php

namespace Base\Controller\Action\Notification;

use App\Entity\User;
use Base\Repository\User\Notifications;
use Base\Screen\Cache;
use Base\Screen\Json;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The bell's read marks, written once for every host (step R29 of the audit
 * "lesringostars face au master plan"; formerly NotificationController::
 * MarkRead()). Body: {token, id?, read?} - one notification of the account,
 * or every unread one when id is absent; read defaults to true, false puts
 * one back to unread. The "notifications" token in the body (the toolbar's
 * data-csrf) is checked through the host's Tokens; without it, or for a
 * visitor, 403. JSON rather than a redirect: the bell updates in place.
 */
#[Route('/notifications/read', name: 'user_notifications_read', methods: ['POST'])]
final class Read
{
    /** The intent of the bell's token. */
    public const TOKEN = 'notifications';

    public function __construct(
        private readonly Access $access,
        private readonly Tokens $tokens,
        private readonly Notifications $notifications,
    ) {
    }

    /** @param array<string, mixed> $body */
    public function __invoke(array $body = []): Json
    {
        $user = self::account($this->access, $this->tokens, $body);
        if (null === $user) {
            return new Json(['success' => false], 403, Cache::private());
        }

        $read = !\array_key_exists('read', $body) || filter_var($body['read'], \FILTER_VALIDATE_BOOLEAN);
        $isRead = null;
        if (!empty($body['id']) && \is_scalar($body['id'])) {
            $notification = $this->notifications->findOneBy(['id' => (int) $body['id'], 'user' => $user]);
            if (null !== $notification) {
                $notification->setIsRead($read);
                $isRead = $read;
            }
        } else {
            foreach ($this->notifications->findBy(['user' => $user, 'isRead' => false]) as $notification) {
                $notification->setIsRead(true);
            }
        }
        $this->notifications->flush();

        return new Json(['success' => true, 'unread' => $this->notifications->countUnreadFor($user), 'isRead' => $isRead], cache: Cache::private());
    }

    /**
     * The account signed in, when the body carries its bell's token; null otherwise.
     *
     * @param array<string, mixed> $body
     */
    public static function account(Access $access, Tokens $tokens, array $body): ?User
    {
        $user = $access->getUser();
        $token = \is_string($body['token'] ?? null) ? $body['token'] : null;

        return $user instanceof User && $tokens->isValid(self::TOKEN, $token) ? $user : null;
    }
}
