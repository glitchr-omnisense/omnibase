<?php

namespace Base\Entity\User;

use App\Entity\User;
use Base\Repository\User\IdentityRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * An account's identity at a provider (Google...): the provider's own
 * identifier of the person ("sub"), which is what links the account - never
 * the address alone (plan "omniauth", Marco's decision QA3). omniauth's
 * Contract\Identities over this table is Base\Auth\UserIdentities.
 *
 * One subject of a provider has one account (unique provider + subject); an
 * account has one identity per provider (unique account + provider, which
 * also serves the account's own lookups). Removing the account removes its
 * identities (ON DELETE CASCADE). The address the provider gave when the link
 * was made, and whether it vouched for it, are kept as they were then; the
 * last use is a sign-in by that identity. User::$identityProvider keeps its
 * own meaning: where the account came from.
 */
#[ORM\Entity(repositoryClass: IdentityRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_userIdentity_provider_subject', columns: ['provider', 'subject'])]
#[ORM\UniqueConstraint(name: 'UNIQ_userIdentity_user_provider', columns: ['user_id', 'provider'])]
class Identity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    protected $user;

    /** The provider's key, as omniauth's OAuthMethod names it ("google"). */
    #[ORM\Column(type: "string", length: 32)]
    protected string $provider;

    /** The person's stable identifier at the provider. */
    #[ORM\Column(type: "string", length: 255)]
    protected string $subject;

    /** The address the provider gave when the link was made. */
    #[ORM\Column(type: "string", length: 180, nullable: true)]
    protected ?string $email = null;

    /** Whether the provider vouched for that address then. */
    #[ORM\Column(type: "boolean")]
    protected bool $emailVerified = false;

    #[ORM\Column(type: "datetime")]
    protected $createdAt;

    #[ORM\Column(type: "datetime", nullable: true)]
    protected $lastUsedAt;

    public function __construct(User $user, string $provider, string $subject, ?string $email = null, bool $emailVerified = false)
    {
        if ('' === $provider || '' === $subject) {
            throw new \InvalidArgumentException('An identity has its provider and its subject.');
        }
        $this->user = $user;
        $this->provider = $provider;
        $this->subject = $subject;
        $this->email = null === $email ? null : mb_strtolower($email);
        $this->emailVerified = $emailVerified;
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?DateTimeInterface
    {
        return $this->lastUsedAt;
    }

    public function markUsed(DateTimeInterface $at): self
    {
        $this->lastUsedAt = DateTime::createFromInterface($at);

        return $this;
    }
}
