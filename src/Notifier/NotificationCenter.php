<?php

namespace Base\Notifier;

use Base\Auth\Account\Outcome;
use Base\Entity\User;
use Base\Repository\User\Notifications;
use Base\Screen\Json;
use Base\Service\Push\WebPushService;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;

/**
 * The notification center's page and the push subscriptions, written once for every host (plan "omnibase agnostic",
 * step F5e, slice 5b; formerly NotificationController::Index(), PushConfig(), PushSubscribe(), PushUnsubscribe() and
 * ServiceWorker()'s file; the bell's payload, read marks and removals are Base\Controller\Action\Notification's):
 * - the page: a visitor signs in first; ?unread=1 narrows it in the query (the list stops at a hundred: hiding the
 *   read rows in the page showed an empty page to anyone whose last hundred were read while older ones waited);
 * - push: the VAPID public key and whether a member may subscribe; a subscription kept or forgotten by the
 *   "notifications" token of the body (JSON, same origin), answered in JSON because the bell updates in place;
 * - the service worker's source, served from the site's root (a worker's scope cannot exceed its own path).
 */
final class NotificationCenter
{
    public const TOKEN = 'notifications';
    public const LIMIT = 100;
    public const TEMPLATE = 'client/user/notifications.html.twig';
    public const SIGN_IN = 'security_login';
    public const SERVICE_WORKER = __DIR__.'/../Resources/sw/sw.js';

    public function __construct(
        private readonly Access $access,
        private readonly Notifications $notifications,
        private readonly WebPushService $webPush,
        private readonly ?Tokens $tokens = null,
    ) {
    }

    /**
     * The page's notifications and the unread count, or where a visitor goes.
     *
     * @return Outcome|array{0: list<object>, 1: int}
     */
    public function page(bool $unreadOnly): Outcome|array
    {
        $user = $this->access->getUser();
        if (!$user instanceof \App\Entity\User) {
            return Outcome::route(self::SIGN_IN);
        }

        return [array_values($this->notifications->findVisibleFor($user, self::LIMIT, 0, $unreadOnly)), $this->notifications->countUnreadFor($user)];
    }

    /** What the browser needs before it can subscribe: the VAPID public key, or null when push is off. */
    public function pushConfig(): Json
    {
        return new Json([
            'enabled' => $this->webPush->isConfigured() && $this->access->getUser() instanceof \App\Entity\User,
            'publicKey' => $this->webPush->getPublicKey(),
        ]);
    }

    /** @param array<string, mixed> $body {token, subscription: PushSubscriptionJSON} */
    public function subscribe(array $body, ?string $userAgent): Json
    {
        $user = $this->member($body);
        if (null === $user) {
            return new Json(['success' => false], 403);
        }
        if (!$this->webPush->isConfigured()) {
            return new Json(['success' => false, 'error' => 'Push is not configured.'], 503);
        }
        try {
            $this->webPush->subscribe($user, (array) ($body['subscription'] ?? []), $userAgent);
        } catch (\InvalidArgumentException $e) {
            return new Json(['success' => false, 'error' => $e->getMessage()], 400);
        }

        return new Json(['success' => true]);
    }

    /** @param array<string, mixed> $body {token, endpoint} */
    public function unsubscribe(array $body): Json
    {
        $user = $this->member($body);
        if (null === $user) {
            return new Json(['success' => false], 403);
        }

        return new Json(['success' => true, 'removed' => $this->webPush->unsubscribe($user, (string) ($body['endpoint'] ?? ''))]);
    }

    /**
     * A request's body: its JSON, else its form fields.
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    public static function body(string $content, array $form): array
    {
        $body = json_decode($content, true);

        return \is_array($body) ? $body : $form;
    }

    /** The service worker's source. */
    public static function serviceWorker(): string
    {
        return (string) file_get_contents(self::SERVICE_WORKER);
    }

    /** @param array<string, mixed> $body */
    private function member(array $body): ?User
    {
        $user = $this->access->getUser();
        $token = $body['token'] ?? null;

        return $user instanceof \App\Entity\User && !empty($token) && (bool) $this->tokens?->isValid(self::TOKEN, (string) $token) ? $user : null;
    }
}
