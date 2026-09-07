<?php

namespace Base\Service\Push;

use App\Entity\User;
use Base\Entity\User\PushSubscription;
use Base\Repository\User\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

/**
 * Web Push delivery (RFC 8030 + VAPID) to the browsers a user enabled
 * notifications in. The service is a no-op until VAPID keys are configured
 * (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY / VAPID_SUBJECT), so the rest of the
 * notification center works without it - the in-app list, the badge - and
 * push simply switches on the day the keys land in .env.local.
 *
 * Generate a pair once per site with `bin/console push:vapid` (or
 * VAPID::createVapidKeys()); rotating them invalidates every subscription,
 * so treat them like a credential, not a config knob.
 */
class WebPushService
{
    protected ?WebPush $client = null;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly PushSubscriptionRepository $repository,
        protected readonly ?LoggerInterface $logger = null,
        protected readonly ?string $publicKey = null,
        protected readonly ?string $privateKey = null,
        protected readonly ?string $subject = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return !empty($this->publicKey) && !empty($this->privateKey) && class_exists(WebPush::class);
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey ?: null;
    }

    public static function createKeys(): array
    {
        return VAPID::createVapidKeys();
    }

    /**
     * How many people would receive a push right now (one per user, however
     * many browsers they enabled it in).
     */
    public function countReachableUsers(): int
    {
        return $this->isConfigured() ? $this->repository->countDistinctUsers() : 0;
    }

    /** @return PushSubscription[] */
    public function subscriptionsOf(User $user): array
    {
        return $this->repository->findBy(["user" => $user]);
    }

    /**
     * Registers (or refreshes) one browser's subscription for a user. The
     * browser may re-send the same endpoint (page reload, permission
     * re-prompt); that is an update, not a duplicate.
     */
    public function subscribe(User $user, array $subscription, ?string $userAgent = null): PushSubscription
    {
        $endpoint = (string) ($subscription["endpoint"] ?? "");
        $keys = $subscription["keys"] ?? [];
        if (!$endpoint || empty($keys["p256dh"]) || empty($keys["auth"])) {
            throw new \InvalidArgumentException("Incomplete push subscription.");
        }

        $row = $this->repository->findOneBy(["endpointHash" => sha1($endpoint)]) ?? new PushSubscription();
        $row->setUser($user)
            ->setEndpoint($endpoint)
            ->setP256dh((string) $keys["p256dh"])
            ->setAuth((string) $keys["auth"])
            ->setUserAgent($userAgent)
            ->touch();

        $this->entityManager->persist($row);
        $this->entityManager->flush();

        return $row;
    }

    public function unsubscribe(User $user, string $endpoint): bool
    {
        $row = $this->repository->findOneBy(["endpointHash" => sha1($endpoint), "user" => $user]);
        if (!$row) {
            return false;
        }

        $this->entityManager->remove($row);
        $this->entityManager->flush();
        return true;
    }

    /**
     * Sends one payload to every browser of every given user. Returns the
     * number of subscriptions accepted by their push service. Dead
     * subscriptions (404/410: the browser revoked it) are deleted on the way.
     *
     * @param User[] $users
     * @param array{title:string, body?:string, url?:string, icon?:string, tag?:string} $payload
     */
    public function sendToUsers(iterable $users, array $payload): int
    {
        if (!$this->isConfigured()) {
            return 0;
        }

        $client = $this->getClient();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $rows = [];
        foreach ($users as $user) {
            foreach ($this->subscriptionsOf($user) as $row) {
                $rows[$row->getEndpointHash()] = $row;
                $client->queueNotification(Subscription::create($row->toArray()), $json, ["TTL" => 86400]);
            }
        }

        if (!$rows) {
            return 0;
        }

        $accepted = 0;
        foreach ($client->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            $row = $rows[sha1($endpoint)] ?? null;

            if ($report->isSuccess()) {
                $accepted++;
                $row?->touch();
                continue;
            }

            if ($report->isSubscriptionExpired() && $row) {
                $this->entityManager->remove($row);
            }
            $this->logger?->warning("Web push rejected: " . $report->getReason(), ["endpoint" => $endpoint]);
        }

        $this->entityManager->flush();
        return $accepted;
    }

    protected function getClient(): WebPush
    {
        if ($this->client === null) {
            $this->client = new WebPush([
                "VAPID" => [
                    "subject" => $this->subject ?: "mailto:noreply@localhost",
                    "publicKey" => $this->publicKey,
                    "privateKey" => $this->privateKey,
                ],
            ]);
            $this->client->setReuseVAPIDHeaders(true);
        }

        return $this->client;
    }
}
