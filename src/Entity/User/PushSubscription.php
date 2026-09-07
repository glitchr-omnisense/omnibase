<?php

namespace Base\Entity\User;

use App\Entity\User;
use Base\Repository\User\PushSubscriptionRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * One browser's Web Push endpoint for one user. A person has one of these
 * per browser they enabled notifications in (phone, laptop, ...), and each
 * one dies on its own when the browser revokes it - the push service then
 * answers 404/410 and WebPushService prunes the row.
 *
 * Endpoint URLs run to ~300 characters, longer than an indexable MySQL
 * unique key on utf8mb4, so uniqueness is enforced on a sha1 of it instead.
 */
#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\Table(name: "userPushSubscription")]
#[ORM\UniqueConstraint(name: "uniq_push_endpoint", columns: ["endpointHash"])]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected $id;

    public function getId(): ?int { return $this->id; }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    protected $user;

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    #[ORM\Column(type: "text")]
    protected $endpoint;

    public function getEndpoint(): string { return $this->endpoint; }
    public function setEndpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;
        $this->endpointHash = sha1($endpoint);
        return $this;
    }

    #[ORM\Column(type: "string", length: 40)]
    protected $endpointHash;

    public function getEndpointHash(): string { return $this->endpointHash; }

    #[ORM\Column(type: "string", length: 255)]
    protected $p256dh;

    public function getP256dh(): string { return $this->p256dh; }
    public function setP256dh(string $p256dh): self { $this->p256dh = $p256dh; return $this; }

    #[ORM\Column(type: "string", length: 255)]
    protected $auth;

    public function getAuth(): string { return $this->auth; }
    public function setAuth(string $auth): self { $this->auth = $auth; return $this; }

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    protected $userAgent;

    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $userAgent): self { $this->userAgent = $userAgent ? mb_substr($userAgent, 0, 255) : null; return $this; }

    #[ORM\Column(type: "datetime")]
    protected $createdAt;

    public function getCreatedAt(): DateTimeInterface { return $this->createdAt; }

    #[ORM\Column(type: "datetime", nullable: true)]
    protected $lastUsedAt;

    public function getLastUsedAt(): ?DateTimeInterface { return $this->lastUsedAt; }
    public function touch(): self { $this->lastUsedAt = new DateTime(); return $this; }

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    /** The shape minishlink/web-push expects. */
    public function toArray(): array
    {
        return [
            "endpoint" => $this->endpoint,
            "keys" => ["p256dh" => $this->p256dh, "auth" => $this->auth],
        ];
    }
}
